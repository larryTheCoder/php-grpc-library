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
    /**
     * Start the call.
     *
     * @param array $metadata Metadata to send with the call, if applicable
     *                        (optional)
     */
    public function start(array $metadata = [], ?Closure $onComplete = null): void
    {
        $this->call->startBatch([
            OP_SEND_INITIAL_METADATA => $metadata,
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
     * @param Closure $onMessage A stream of response values.
     */
    public function responses(Closure $onMessage): void
    {
        Await::f2c(function () use ($onMessage): Generator {
            $continue = true;

            repeat:

            // In a non-async operation, this will become an infinite recursion.
            $this->read(yield);
            $read_event = yield Await::ONCE;

            $response = $read_event->message;
            while ($response !== null && $continue) {
                $continue = $onMessage($this->_deserializeResponse($response));

                goto repeat;
            }
        });
    }

    /**
     * Read the response of a request from a callable function. This method may perform thread-blocking
     * operation if "client_async" is set to false.
     *
     * @param Closure $callback (Response data, Status)
     * @return void
     */
    public function read(Closure $callback): void
    {
        $batch = [OP_RECV_MESSAGE => true];
        if ($this->metadata === null) {
            $batch[OP_RECV_INITIAL_METADATA] = true;
        }

        $this->call->startBatch($batch, function ($event = null) use ($callback) {
            if ($event !== null) {
                if ($this->metadata === null) {
                    $this->metadata = $event->metadata;
                }

                $callback($this->_deserializeResponse($event->message));
            }

            throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
        });
    }

    /**
     * Write a single message to the server. This cannot be called after writesDone is called.
     *
     * @param mixed $data The data to write
     * @param array $options An array of options, possible keys: 'flags' => a number (optional)
     * @param Closure|null $onComplete Called when the request were completed.
     */
    public function write($data, array $options = [], ?Closure $onComplete = null): void
    {
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
     * Indicate that no more writes will be sent.
     */
    public function writesDone(Closure $onComplete): void
    {
        $this->call->startBatch([
            OP_SEND_CLOSE_FROM_CLIENT => true,
        ], function ($event = null) use ($onComplete) {
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            $onComplete();
        });
    }

    /**
     * Returns a status when the server has sent it to the client.
     *
     * @param Closure $onComplete The status object, with integer $code, string $details, and array $metadata members
     *
     * @phpstan-param Closure(mixed): void $onComplete
     */
    public function getStatus(Closure $onComplete): void
    {
        $this->call->startBatch([
            OP_RECV_STATUS_ON_CLIENT => true,
        ], function ($event = null) use ($onComplete) {
            if ($event === null) {
                throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
            }

            $this->trailing_metadata = $event->status->metadata;

            $onComplete($event->status);
        });
    }
}
