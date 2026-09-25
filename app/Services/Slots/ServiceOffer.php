<?php

namespace App\Services\Slots;

/**
 * Итоговые условия услуги у конкретного мастера.
 */
final readonly class ServiceOffer
{
    public function __construct(
        public int $durationMinutes,
        public int $bufferMinutes,
        public int $price,
    ) {}
}
