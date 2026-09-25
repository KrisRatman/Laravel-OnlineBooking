<?php

namespace App\Services\Slots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Полуоткрытый интервал [start, end): запись 10:00–11:00 и запись 11:00–12:00 не пересекаются.
 */
final readonly class Interval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException("Конец интервала ({$end}) должен быть позже начала ({$start}).");
        }
    }

    public static function fromMinutes(CarbonImmutable $start, int $minutes): self
    {
        return new self($start, $start->addMinutes($minutes));
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function contains(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->start) && $other->end->lessThanOrEqualTo($this->end);
    }

    /** @param iterable<self> $intervals */
    public function overlapsAny(iterable $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($this->overlaps($interval)) {
                return true;
            }
        }

        return false;
    }
}
