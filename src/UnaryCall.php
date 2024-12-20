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
use Grpc\Call\ServerCallInterface;
use Grpc\Status\RpcCallStatus;
use LogicException;

/**
 * Represents an active call that sends a single message and then gets a
 * single response.
 *
 * @phpstan-template TReturn
 */
class UnaryCall extends AbstractCall implements ServerCallInterface
{
    public function isServerReady(): bool
    {
        return true; // The server is always ready
    }

    /**
     * Start the call.
     *
     * @param mixed $data The data to send
     * @param array $metadata Metadata to send with the call, if applicable
     *                        (optional)
     * @param array $options An array of options, possible keys:
     *                        'flags' => a number (optional)
     */
    public function start($data, array $metadata = [], array $options = [])
    {
        $message_array = ['message' => $this->_serializeMessage($data)];
        if (isset($options['flags'])) {
            $message_array['flags'] = $options['flags'];
        }
        $this->call->startBatch([
            OP_SEND_INITIAL_METADATA => $metadata,
            OP_SEND_MESSAGE => $message_array,
            OP_SEND_CLOSE_FROM_CLIENT => true,
            OP_RECV_INITIAL_METADATA => true
        ], function ($event) {
            $this->metadata = $event->metadata;
        });
    }

    /**
     * @phpstan-param Closure(TReturn, RpcCallStatus): void $onMessage
     */
    public function onStreamNext(Closure $onMessage): void
    {
        $batch = [
            OP_RECV_MESSAGE => true,
            OP_RECV_STATUS_ON_CLIENT => true,
        ];

        $this->call->startBatch($batch, function ($event) use ($onMessage) {
            $status = $event->status;
            $this->trailing_metadata = $status->metadata;

            $onMessage($this->_deserializeResponse($event->message), new RpcCallStatus($status->code, $status->reason, $status->details));
        });
    }

    public function onStreamCompleted(Closure $onCompleted): void
    {
        throw new LogicException("Stream completion is always triggered immediately after receiving a message by remote server.");
    }
}
