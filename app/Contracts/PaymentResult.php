<?php

namespace App\Contracts;

/**
 * Rezultat poziva payment providera. State machine: success → reservation; failed → failed; timeout → late_success.
 */
final readonly class PaymentResult
{
    public const string SUCCESS = 'success';
    public const string FAILED = 'failed';
    public const string TIMEOUT = 'timeout';

    public function __construct(
        public string $status,
        public ?string $message = null,
    ) {}

    public static function success(?string $message = null): self
    {
        return new self(self::SUCCESS, $message);
    }

    public static function failed(?string $message = null): self
    {
        return new self(self::FAILED, $message);
    }

    public static function timeout(?string $message = null): self
    {
        return new self(self::TIMEOUT, $message);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    public function isTimeout(): bool
    {
        return $this->status === self::TIMEOUT;
    }
}
