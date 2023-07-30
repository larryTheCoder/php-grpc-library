<?php
/*
 *
 * Copyright 2015 gRPC authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Grpc;

use Closure;
use Generator;
use RuntimeException;
use SOFe\AwaitGenerator\Await;

/**
 * Represents an active call that allows for sending and receiving messages
 * in streams in any order.
 */
class BidiStreamingCall extends AbstractCall
{
    /** @var bool */
    private $write_active = true;
    /** @var bool */
    private $read_active = true;
    /** @var Closure */
    private $on_close_callback;

    /**
     * @internal
     */
    public function start(array $metadata = []): void
    {
        Await::f2c(function () use ($metadata): Generator {
            $this->call->startBatch([
                OP_SEND_INITIAL_METADATA => $metadata,
                OP_RECV_INITIAL_METADATA => true,
            ], yield);

            $event = yield Await::ONCE;
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            $this->metadata = $event->metadata;
            $this->call->startBatch([OP_RECV_STATUS_ON_CLIENT => true], yield);

            $event = yield Await::ONCE;
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            $this->read_active = false;

            ($this->on_close_callback)($event->status->code, $event->status->reason, $event->status->details);
        });
    }

    /**
     * Listen for a stream of messages sent by the server. The stream of message will continue to flow
     * until the call itself is closed or terminated.
     *
     * @param Closure $onMessage The stream of messages.
     */
    public function onStreamNext(Closure $onMessage): void
    {
        Await::f2c(function () use ($onMessage): Generator {
            repeat:

            // In a non-async operation, this will become an infinite recursion.
            $batch = [OP_RECV_MESSAGE => true];

            $this->call->startBatch($batch, yield);
            $event = yield Await::ONCE;

            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            if ($this->read_active && $event->message !== null) {
                $onMessage($this->_deserializeResponse($event->message));
                goto repeat;
            }
        });
    }

    /**
     * Listen for call completion by the server. The callback will indicate that there will be no
     * writes by the server after the callback is called.
     *
     * @phpstan-param Closure(int, string, string): void $onCompleted
     */
    public function onStreamCompleted(Closure $onCompleted): void
    {
        $this->on_close_callback = $onCompleted;
    }

    /**
     * Write a single message to the server. This cannot be called after writesDone is called.
     *
     * @param mixed $data The data to write
     * @param array $options An array of options, possible keys: 'flags' => a number (optional)
     * @param Closure|null $onComplete Called when the request were completed.
     */
    public function onClientNext($data, array $options = [], ?Closure $onComplete = null): void
    {
        if (!$this->write_active) {
            throw new RuntimeException("Cannot write more messages to the server after writesDone.");
        }

        $message_array = ['message' => $this->_serializeMessage($data)];
        if (array_key_exists('flags', $options)) {
            $message_array['flags'] = $options['flags'];
        }

        $this->call->startBatch([
            OP_SEND_MESSAGE => $message_array,
        ], function ($event = null) use ($onComplete) {
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            if ($onComplete !== null) {
                $onComplete();
            }
        });
    }

    /**
     * Indicate that no more writes will be sent, but the server will still be able to send messages
     */
    public function onClientCompleted(Closure $onComplete): void
    {
        $this->call->startBatch([
            OP_SEND_CLOSE_FROM_CLIENT => true,
        ], function ($event = null) use ($onComplete) {
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            $this->write_active = false;

            $onComplete();
        });
    }
}
