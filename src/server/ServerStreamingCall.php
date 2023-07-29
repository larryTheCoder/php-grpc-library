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

namespace Grpc\server;

use Closure;
use Generator;
use Grpc\AbstractCall;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use const Grpc\OP_RECV_INITIAL_METADATA;
use const Grpc\OP_RECV_MESSAGE;
use const Grpc\OP_RECV_STATUS_ON_CLIENT;
use const Grpc\OP_SEND_CLOSE_FROM_CLIENT;
use const Grpc\OP_SEND_INITIAL_METADATA;
use const Grpc\OP_SEND_MESSAGE;

/**
 * Represents an active call that sends a single message and then gets a
 * stream of responses.
 */
class ServerStreamingCall extends AbstractCall
{
    /**
     * Start the call.
     *
     * @param mixed $data     The data to send
     * @param array $metadata Metadata to send with the call, if applicable
     *                        (optional)
     * @param array $options  An array of options, possible keys:
     *                        'flags' => a number (optional)
     */
    public function start($data, array $metadata = [], array $options = [], ?Closure $onComplete = null)
    {
        $message_array = ['message' => $this->_serializeMessage($data)];
        if (array_key_exists('flags', $options)) {
            $message_array['flags'] = $options['flags'];
        }
        $this->call->startBatch([
            OP_SEND_INITIAL_METADATA => $metadata,
            OP_SEND_MESSAGE => $message_array,
            OP_SEND_CLOSE_FROM_CLIENT => true,
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
            $batch = [OP_RECV_MESSAGE => true];
            if ($this->metadata === null) {
                $batch[OP_RECV_INITIAL_METADATA] = true;
            }

            $continue = true;

            repeat:

            // In a non-async operation, this will become an infinite recursion.
            $this->call->startBatch($batch, yield);
            $read_event = yield Await::ONCE;

            $response = $read_event->message;
            while ($response !== null && $continue) {
                $continue = $onMessage($this->_deserializeResponse($response));
                $batch = [OP_RECV_MESSAGE => true];

                goto repeat;
            }
        });
    }

    /**
     * Wait for the server to send the status, and return it.
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

    /**
     * @return mixed The metadata sent by the server
     */
    public function getMetadata()
    {
        if ($this->metadata === null) {
            $this->call->startBatch([
                OP_RECV_INITIAL_METADATA => true
            ], function ($event = null) {
                if ($event === null) {
                    throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
                }

                $this->metadata = $event->metadata;
            });
        }

        return $this->metadata;
    }
}
