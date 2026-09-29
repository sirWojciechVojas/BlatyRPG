<?php

namespace App\Services\Consumption;

final class ConsumptionException extends \RuntimeException
{
    private $status;
    private $domainCode;

    public function __construct(string $code, string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->domainCode = $code;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function domainCode(): string
    {
        return $this->domainCode;
    }
}
