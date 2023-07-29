<?php


namespace Grpc {
    const CALL_OK = 0;

    const CALL_ERROR = 1;

    const CALL_ERROR_NOT_ON_SERVER = 2;

    const CALL_ERROR_NOT_ON_CLIENT = 3;

    const CALL_ERROR_ALREADY_INVOKED = 5;

    const CALL_ERROR_NOT_INVOKED = 6;

    const CALL_ERROR_ALREADY_FINISHED = 7;

    const CALL_ERROR_TOO_MANY_OPERATIONS = 8;

    const CALL_ERROR_INVALID_FLAGS = 9;

    const WRITE_BUFFER_HINT = 1;

    const WRITE_NO_COMPRESS = 2;

    const STATUS_OK = 0;

    const STATUS_CANCELLED = 1;

    const STATUS_UNKNOWN = 2;

    const STATUS_INVALID_ARGUMENT = 3;

    const STATUS_DEADLINE_EXCEEDED = 4;

    const STATUS_NOT_FOUND = 5;

    const STATUS_ALREADY_EXISTS = 6;

    const STATUS_PERMISSION_DENIED = 7;

    const STATUS_UNAUTHENTICATED = 16;

    const STATUS_RESOURCE_EXHAUSTED = 8;

    const STATUS_FAILED_PRECONDITION = 9;

    const STATUS_ABORTED = 10;

    const STATUS_OUT_OF_RANGE = 11;

    const STATUS_UNIMPLEMENTED = 12;

    const STATUS_INTERNAL = 13;

    const STATUS_UNAVAILABLE = 14;

    const STATUS_DATA_LOSS = 15;

    const OP_SEND_INITIAL_METADATA = 0;

    const OP_SEND_MESSAGE = 1;

    const OP_SEND_CLOSE_FROM_CLIENT = 2;

    const OP_SEND_STATUS_FROM_SERVER = 3;

    const OP_RECV_INITIAL_METADATA = 4;

    const OP_RECV_MESSAGE = 5;

    const OP_RECV_STATUS_ON_CLIENT = 6;

    const OP_RECV_CLOSE_ON_SERVER = 7;

    const CHANNEL_IDLE = 0;

    const CHANNEL_CONNECTING = 1;

    const CHANNEL_READY = 2;

    const CHANNEL_TRANSIENT_FAILURE = 3;

    const CHANNEL_FATAL_FAILURE = 4;

    const VERSION = '1.57.0dev';
}

namespace Grpc {
    class Call
    {
        protected $channel;

        public static function drainCompletionQueue(int $timeout_micros): bool
        {
        }

        public function __construct(\Grpc\Channel $channel, string $method, \Grpc\Timeval $deadline, ?string $host_override = NULL, bool $client_async = false)
        {
        }

        public function startBatch(array $ops, callable $callback): void
        {
        }

        public function getPeer(): string
        {
        }

        public function cancel(): void
        {
        }

        public function setCredentials(\Grpc\ChannelCredentials $credentials): int
        {
        }
    }
}

namespace Grpc {
    class Channel
    {

        public function __construct($target, $args)
        {
        }

        public function getTarget()
        {
        }

        public function getConnectivityState($try_to_connect = null)
        {
        }

        public function watchConnectivityState($last_state, $deadline)
        {
        }

        public function close()
        {
        }
    }
}

namespace Grpc {
    class Server
    {

        public function __construct($args = null)
        {
        }

        public function requestCall()
        {
        }

        public function addHttp2Port($addr)
        {
        }

        public function addSecureHttp2Port($addr, $server_creds)
        {
        }

        public function start()
        {
        }
    }
}

namespace Grpc {
    class Timeval
    {

        public function __construct($microseconds)
        {
        }

        public function add($timeval)
        {
        }

        public static function compare($a_timeval, $b_timeval)
        {
        }

        public static function infFuture()
        {
        }

        public static function infPast()
        {
        }

        public static function now()
        {
        }

        public static function similar($a_timeval, $b_timeval, $threshold_timeval)
        {
        }

        public function sleepUntil()
        {
        }

        public function subtract($timeval)
        {
        }

        public static function zero()
        {
        }
    }
}

namespace Grpc {
    class ChannelCredentials
    {

        public static function setDefaultRootsPem($pem_roots)
        {
        }

        public static function isDefaultRootsPemSet()
        {
        }

        public static function invalidateDefaultRootsPem()
        {
        }

        public static function createDefault()
        {
        }

        public static function createSsl($pem_root_certs = null, $pem_private_key = null, $pem_cert_chain = null)
        {
        }

        public static function createComposite($channel_creds, $call_creds)
        {
        }

        public static function createInsecure()
        {
        }

        public static function createXds(?\Grpc\ChannelCredentials $fallback_creds)
        {
        }
    }
}

namespace Grpc {
    class CallCredentials
    {

        public static function createComposite($creds1, $creds2)
        {
        }

        public static function createFromPlugin($callback)
        {
        }
    }
}

namespace Grpc {
    class ServerCredentials
    {

        public static function createSsl($pem_root_certs, $pem_private_key, $pem_cert_chain)
        {
        }
    }
}