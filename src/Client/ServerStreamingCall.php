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

namespace Grpc\Client;

use Closure;
use Generator;
use Grpc\AbstractCall;
use Grpc\Call\ServerCallInterface;
use Grpc\Status\RpcCallStatus;
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
 *
 * @phpstan-template TReturn
 */
class ServerStreamingCall extends AbstractCall implements ServerCallInterface
{
    /** @var bool */
    private $read_active = true;
    /** @var Closure */
    private $on_close_callback;

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
        Await::f2c(function () use ($data, $metadata, $options): Generator {
            $message_array = ['message' => $this->_serializeMessage($data)];
            if (array_key_exists('flags', $options)) {
                $message_array['flags'] = $options['flags'];
            }

            $this->call->startBatch([
                OP_SEND_INITIAL_METADATA => $metadata,
                OP_SEND_MESSAGE => $message_array,
                OP_SEND_CLOSE_FROM_CLIENT => true,
                OP_RECV_INITIAL_METADATA => true
            ], yield);

            $event = yield Await::ONCE;

            $this->metadata = $event->metadata;
            $this->call->startBatch([OP_RECV_STATUS_ON_CLIENT => true], yield);

            $event = yield Await::ONCE;

            $this->read_active = false;

            ($this->on_close_callback)(new RpcCallStatus($event->status->code, $event->status->reason, $event->status->details));
        });
    }

    /**
     * Listen for a stream of messages sent by the server. The stream of message will continue to flow
     * until the server closed its connection.
     *
     * @phpstan-param Closure(TReturn): void $onMessage
     */
    public function onStreamNext(Closure $onMessage): void
    {
        Await::f2c(function () use ($onMessage): Generator {
            repeat:

            // In a non-async operation, this will become an infinite recursion.
            $this->call->startBatch([OP_RECV_MESSAGE => true], yield);
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
}
