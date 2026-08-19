<?php

namespace App\Services\Scene;

class SceneException extends \RuntimeException
{
    private $status;
    private $errorCode;
    private $details;

    public function __construct(string $errorCode, string $message, int $status, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function details(): array
    {
        return $this->details;
    }
}
