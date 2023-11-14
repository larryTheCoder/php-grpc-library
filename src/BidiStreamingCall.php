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
use Grpc\Call\ClientCallInterface;
use Grpc\Call\ServerCallInterface;
use Grpc\Status\RpcCallStatus;
use RuntimeException;
use SOFe\AwaitGenerator\Await;

/**
 * Represents an active call that allows for sending and receiving messages
 * in streams in any order.
 *
 * @phpstan-template TSend
 * @phpstan-template TReceive
 */
class BidiStreamingCall extends AbstractCall implements ClientCallInterface, ServerCallInterface
{
    /** @var bool */
    private $write_active = false;
    /** @var bool */
    private $read_active = true;
    /** @var Closure */
    private $on_close_callback;
    /** @var Closure */
    private $on_ready_callback;

    /**
     * @internal
     */
    public function start(array $metadata = []): void
    {
        Await::f2c(function () use ($metadata): Generator {
            $this->call->startBatch([
                OP_SEND_INITIAL_METADATA => $metadata
            ], yield);

            yield Await::ONCE;

            $this->write_active = true;

            ($this->on_ready_callback)();

            $this->call->startBatch([
                OP_RECV_INITIAL_METADATA => true,
                OP_RECV_STATUS_ON_CLIENT => true
            ], yield);

            $event = yield Await::ONCE;

            $this->metadata = $event->metadata;

            $this->read_active = false;

            ($this->on_close_callback)(new RpcCallStatus($event->status->code, $event->status->reason, $event->status->details));
        });
    }

    /**
     * Listen for a stream of messages sent by the server. The stream of message will continue to flow
     * until the call itself is closed or terminated.
     *
     * @phpstan-param Closure(TReceive, RpcCallStatus): void $onMessage
     */
    public function onStreamNext(Closure $onMessage): void
    {
        Await::f2c(function () use ($onMessage): Generator {
            repeat:

            // In a non-async operation, this will become an infinite recursion.
            $batch = [OP_RECV_MESSAGE => true];

            $this->call->startBatch($batch, yield);
            $event = yield Await::ONCE;

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
     * @phpstan-param Closure(RpcCallStatus): void $onCompleted
     */
    public function onStreamCompleted(Closure $onCompleted): void
    {
        $this->on_close_callback = $onCompleted;
    }

    public function isClientReady(): bool
    {
        return $this->write_active;
    }

    public function onClientReady(Closure $onReady): void
    {
        if ($this->write_active) {
            ($onReady)();
        } else {
            $this->on_ready_callback = $onReady;
        }
    }

    /**
     * Write a single message to the server. This cannot be called after writesDone is called.
     *
     * @param mixed $data The data to write
     * @param array $options An array of options, possible keys: 'flags' => a number (optional)
     * @param Closure|null $onComplete Called when the request were completed.
     *
     * @phpstan-param TSend $data
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
        ], function () use ($onComplete) {
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
        ], function () use ($onComplete) {
            $this->write_active = false;

            $onComplete();
        });
    }
}
