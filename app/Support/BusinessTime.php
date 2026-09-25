<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Перевод между UTC (как хранится в БД) и часовым поясом бизнеса (как видят люди).
 */
final class BusinessTime
{
    public static function timezone(): string
    {
        return config('booking.timezone');
    }

    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone());
    }

    /** Сегодняшняя дата у бизнеса (Y-m-d). В UTC может быть ещё вчера. */
    public static function today(?CarbonInterface $now = null): string
    {
        return self::local($now ?? CarbonImmutable::now())->toDateString();
    }

    /**
     * Начало и конец местных суток в UTC: [00:00 этой даты, 00:00 следующей).
     * В день перевода часов сутки длятся 23 или 25 часов.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayBounds(string $date): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());

        return [$start->utc(), $start->addDay()->utc()];
    }
}
