<?php

namespace Grpc\Call;

use Closure;

interface ServerCallInterface
{
    /**
     * @return bool Indicate the server is ready to send messages to the client.
     */
    public function isServerReady(): bool;

    /**
     * Method that will be called when a message is sent by the remote server.
     * The method implementation varies with other call method.
     *
     * @param Closure $onMessage The stream of messages.
     */
    public function onStreamNext(Closure $onMessage): void;

    /**
     * A callback method to indicate that no more writes can be sent by the server and the client.
     * The client may not send any messages after the stream completes.
     *
     * @param Closure $onCompleted
     * @return void
     */
    public function onStreamCompleted(Closure $onCompleted): void;
}