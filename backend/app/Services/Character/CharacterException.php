<?php

namespace App\Services\Character;

use RuntimeException;

final class CharacterException extends RuntimeException
{
    private $errorCode;
    private $status;
    private $errors;

    public function __construct(
        string $errorCode,
        string $message,
        int $status,
        array $errors = []
    ) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->errors = $errors;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
