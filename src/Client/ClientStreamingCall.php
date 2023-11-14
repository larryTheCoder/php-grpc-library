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
use Grpc\Call\ClientCallInterface;
use Grpc\Status\RpcCallStatus;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use const Grpc\OP_RECV_INITIAL_METADATA;
use const Grpc\OP_RECV_MESSAGE;
use const Grpc\OP_RECV_STATUS_ON_CLIENT;
use const Grpc\OP_SEND_CLOSE_FROM_CLIENT;
use const Grpc\OP_SEND_INITIAL_METADATA;
use const Grpc\OP_SEND_MESSAGE;

/**
 * Represents an active call that sends a stream of messages and then gets
 * a single response.
 *
 * @phpstan-template TSend
 * @phpstan-template TReceive
 */
class ClientStreamingCall extends AbstractCall implements ClientCallInterface
{
    /** @var bool */
    private $write_active = false;
    /** @var Closure */
    private $on_ready_callback;

    /**
     * Start the gRPC call.
     *
     * @param array $metadata Metadata to send with the call, if applicable (optional)
     */
    public function start(array $metadata = [])
    {
        Await::f2c(function () use ($metadata): Generator {
            $this->call->startBatch([
                OP_SEND_INITIAL_METADATA => $metadata
            ], yield);

            yield Await::ONCE;

            $this->write_active = true;

            ($this->on_ready_callback)();
        });
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
     * Ends client streaming call and wait for a response by the remote server.
     * This call will no longer be usable after calling the method.
     *
     * @param Closure $onComplete (Response data, Status)
     * @return void
     *
     * @phpstan-param Closure(TReceive, RpcCallStatus): void $onMessage
     */
    public function onClientCompleted(Closure $onComplete): void
    {
        $this->call->startBatch([
            OP_SEND_CLOSE_FROM_CLIENT => true,
            OP_RECV_INITIAL_METADATA => true,
            OP_RECV_MESSAGE => true,
            OP_RECV_STATUS_ON_CLIENT => true,
        ], function ($event) use ($onComplete) {
            $this->write_active = false;

            $this->metadata = $event->metadata;

            $status = $event->status;
            $this->trailing_metadata = $status->metadata;

            $onComplete($this->_deserializeResponse($event->message), new RpcCallStatus($status->code, $status->reason, $status->details));
        });
    }
}
