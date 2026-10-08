<?php

namespace App\Payments;

final class GatewayResult
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $reference = null,
        public readonly array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }
}
