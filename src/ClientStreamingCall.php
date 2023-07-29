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
use RuntimeException;
use const Grpc\OP_RECV_INITIAL_METADATA;
use const Grpc\OP_RECV_MESSAGE;
use const Grpc\OP_RECV_STATUS_ON_CLIENT;
use const Grpc\OP_SEND_CLOSE_FROM_CLIENT;
use const Grpc\OP_SEND_INITIAL_METADATA;
use const Grpc\OP_SEND_MESSAGE;

/**
 * Represents an active call that sends a stream of messages and then gets
 * a single response.
 */
class ClientStreamingCall extends AbstractCall
{
    /**
     * Start the gRPC call.
     *
     * @param array $metadata Metadata to send with the call, if applicable (optional)
     */
    public function start(array $metadata = [], ?Closure $onComplete = null)
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
     * Write a single message to the server. This cannot be called after read is called.
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
     * Read the response of a request from a callable function. This method may perform thread-blocking
     * operation if "client_async" is set to false.
     *
     * @param Closure $callback (Response data, Status)
     * @return void
     */
    public function read(Closure $callback)
    {
        $this->call->startBatch([
            OP_SEND_CLOSE_FROM_CLIENT => true,
            OP_RECV_INITIAL_METADATA => true,
            OP_RECV_MESSAGE => true,
            OP_RECV_STATUS_ON_CLIENT => true,
        ], function ($event = null) use ($callback) {
            if ($event !== null) {
                $this->metadata = $event->metadata;

                $status = $event->status;
                $this->trailing_metadata = $status->metadata;

                return [$this->_deserializeResponse($event->message), $status];
            }

            throw new RuntimeException("The gRPC request was unsuccessful, this may indicate that gRPC service is shutting down or timed out.");
        });
    }
}
