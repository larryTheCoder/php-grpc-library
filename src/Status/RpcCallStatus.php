<?php

namespace Grpc\Status;

class RpcCallStatus
{
    /** @var int */
    private $code;
    /** @var string */
    private $code_parsed;
    /** @var string */
    private $details;

    public function __construct(int $code, string $codeParsed, string $details)
    {
        $this->code = $code;
        $this->code_parsed = $codeParsed;
        $this->details = $details;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getCodeParsed(): string
    {
        return $this->code_parsed;
    }

    /**
     * @return string
     */
    public function getDetails(): string
    {
        return $this->details;
    }
}