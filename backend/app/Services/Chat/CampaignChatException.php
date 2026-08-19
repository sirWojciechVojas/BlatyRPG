<?php

namespace App\Services\Chat;

class CampaignChatException extends \RuntimeException
{
    private $errorCode;
    private $status;
    private $details;

    public function __construct(string $errorCode, string $message, int $status, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->details;
    }
}
