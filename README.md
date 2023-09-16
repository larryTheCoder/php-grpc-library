# PHP-gRPC library

Unofficial work of Google PHP gRPC implementation for asynchronous calls. The implementation is taken from @arnaud-lb [php-async](https://github.com/arnaud-lb/grpc/tree/php-async) branch.

Here lies the documentation to interact with my own [grpc binding](https://github.com/larryTheCoder/php-grpc) library.

### Asynchronous calls
PHP does not have an event-loop, therefore user-space event loop is required to check if a call is completed. The following must be called periodically:

```php
Call::drainCompletionQueue(PHP_INT_MIN);
```

The first parameter specifies the time to wait for any queue to complete. In this case, the completion queue will check if [any calls is complete once](https://github.com/larryTheCoder/php-grpc/blob/main/src/completion_queue.c#L49-L76).

### Client gRPC calls

To establish a bidirectional call, user must wait for the connection to be established to the remote server before sending any messages.
This can be achieved by providing a callback to `BidiStreamingCall::onClientReady(Closure)`.
Alternatively, `BidiStreamingCall::isReady()` can be used to check if the call is ready to send any message to the remote server.

| [ClientStreamingCall](https://github.com/larryTheCoder/php-grpc-library/blob/main/src/ClientStreamingCall.php) | [BidiStreamingCall](https://github.com/larryTheCoder/php-grpc-library/blob/main/src/BidiStreamingCall.php) |
|:--------------------------------------------------------------------------------------------------------------:|:----------------------------------------------------------------------------------------------------------:|
