<?php

namespace App\Services\Slots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Расписание мастера на одну дату: рабочие часы и перерывы в местном времени бизнеса.
 * Переводит их в UTC-интервалы именно для этой даты, поэтому перевод часов учитывается сам.
 */
final readonly class DaySchedule
{
    /**
     * @param  string  $date  Y-m-d в поясе бизнеса
     * @param  list<array{0: string, 1: string}>  $workingHours  [['10:00', '19:00'], ...]
     * @param  list<array{0: string, 1: string}>  $breaks  [['14:00', '15:00'], ...]
     */
    public function __construct(
        public string $date,
        public string $timezone,
        public array $workingHours,
        public array $breaks = [],
    ) {}

    public static function dayOff(string $date, string $timezone): self
    {
        return new self($date, $timezone, []);
    }

    public function isDayOff(): bool
    {
        return $this->workingHours === [];
    }

    /** @return list<Interval> */
    public function workingIntervals(): array
    {
        return array_map($this->toInterval(...), $this->workingHours);
    }

    /** @return list<Interval> */
    public function breakIntervals(): array
    {
        return array_map($this->toInterval(...), $this->breaks);
    }

    /** @param array{0: string, 1: string} $range */
    private function toInterval(array $range): Interval
    {
        [$from, $to] = $range;

        $start = $this->moment($from);
        $end = $this->moment($to);

        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException("Интервал {$from}–{$to} на {$this->date}: конец должен быть позже начала.");
        }

        return new Interval($start, $end);
    }

    /** '10:00' или '10:00:00' из БД → момент в UTC. */
    private function moment(string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->date.' '.substr($time, 0, 5), $this->timezone)->utc();
    }
}
