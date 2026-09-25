<?php

namespace App\Services\Slots;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Главная логика проекта: какие времена начала свободны у мастера в заданный день.
 *
 * Чистый класс без БД: на вход расписание дня и занятое время, на выход — начала слотов в UTC.
 *
 * Слот с началом T подходит, если:
 *  1. услуга [T, T + длительность) целиком внутри рабочего интервала;
 *  2. услуга не задевает перерыв;
 *  3. услуга вместе с буфером [T, T + длительность + буфер) не задевает занятое время;
 *  4. T не раньше $notBefore (прошлое и минимальный запас до начала).
 *
 * Сетка строится от начала каждого рабочего интервала с шагом $stepMinutes,
 * поэтому клиенту предлагаются «ровные» времена: 10:00, 10:15, 10:30...
 */
final class SlotCalculator
{
    /**
     * @param  list<Interval>  $busy  занятое записями время, уже с их буферами
     * @return list<CarbonImmutable> начала свободных слотов в UTC по возрастанию
     */
    public function calculate(
        DaySchedule $day,
        int $durationMinutes,
        array $busy = [],
        int $stepMinutes = 15,
        int $bufferMinutes = 0,
        ?CarbonImmutable $notBefore = null,
    ): array {
        if ($durationMinutes <= 0 || $stepMinutes <= 0 || $bufferMinutes < 0) {
            throw new InvalidArgumentException('Длительность и шаг должны быть больше нуля, буфер — не меньше нуля.');
        }

        $breaks = $day->breakIntervals();
        $slots = [];

        foreach ($day->workingIntervals() as $period) {
            for (
                $start = $period->start;
                $start->addMinutes($durationMinutes)->lessThanOrEqualTo($period->end);
                $start = $start->addMinutes($stepMinutes)
            ) {
                if ($notBefore !== null && $start->lessThan($notBefore)) {
                    continue;
                }

                $service = Interval::fromMinutes($start, $durationMinutes);

                if ($service->overlapsAny($breaks)) {
                    continue;
                }

                if (Interval::fromMinutes($start, $durationMinutes + $bufferMinutes)->overlapsAny($busy)) {
                    continue;
                }

                $slots[$start->getTimestamp()] = $start;
            }
        }

        // Рабочие интервалы могут прийти не по порядку или пересекаться — убираем дубли и сортируем.
        ksort($slots);

        return array_values($slots);
    }
}
