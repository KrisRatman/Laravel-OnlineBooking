<?php

use App\Services\Slots\DaySchedule;
use App\Services\Slots\Interval;
use App\Services\Slots\SlotCalculator;
use Carbon\CarbonImmutable;

const DATE = '2026-10-01';
const TZ = 'Europe/Moscow';

/** Момент местного времени бизнеса → UTC. */
function at(string $time, string $date = DATE, string $timezone = TZ): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", $timezone)->utc();
}

/** Занятое время, как его передаёт SlotService. */
function busy(string $from, string $to): Interval
{
    return new Interval(at($from), at($to));
}

/**
 * Слоты в местном времени для удобного сравнения.
 *
 * @param  list<array{0: string, 1: string}>  $hours
 * @param  list<array{0: string, 1: string}>  $breaks
 * @return list<string>
 */
function slots(array $hours, int $duration, array $busy = [], array $breaks = [], int $step = 15, int $buffer = 0, ?CarbonImmutable $notBefore = null): array
{
    $day = new DaySchedule(DATE, TZ, $hours, $breaks);

    return array_map(
        fn (CarbonImmutable $slot) => $slot->setTimezone(TZ)->format('H:i'),
        (new SlotCalculator)->calculate($day, $duration, $busy, $step, $buffer, $notBefore),
    );
}

describe('границы дня', function () {
    it('нарезает свободный день по сетке', function () {
        expect(slots([['10:00', '12:00']], 60))
            ->toBe(['10:00', '10:15', '10:30', '10:45', '11:00']);
    });

    it('пускает запись, которая заканчивается ровно в момент закрытия, и не пускает ту, что вылезает', function () {
        $slots = slots([['10:00', '19:00']], 90);

        expect($slots)->toContain('17:30')
            ->not->toContain('17:45')
            ->and(end($slots))->toBe('17:30');
    });

    it('не даёт слотов, если услуга длиннее рабочего дня', function () {
        expect(slots([['10:00', '11:00']], 90))->toBe([]);
    });

    it('не даёт слотов в выходной', function () {
        $day = DaySchedule::dayOff(DATE, TZ);

        expect((new SlotCalculator)->calculate($day, 60))->toBe([]);
    });

    it('строит сетку от начала каждого рабочего интервала', function () {
        expect(slots([['10:00', '11:00'], ['16:10', '17:40']], 60, step: 30))
            ->toBe(['10:00', '16:10', '16:40']);
    });

    it('убирает дубли и сортирует, если рабочие интервалы пришли вразнобой и пересекаются', function () {
        expect(slots([['12:00', '13:00'], ['10:00', '12:30']], 60, step: 30))
            ->toBe(['10:00', '10:30', '11:00', '11:30', '12:00']);
    });

    it('учитывает шаг сетки', function () {
        expect(slots([['10:00', '12:00']], 60, step: 30))->toBe(['10:00', '10:30', '11:00'])
            ->and(slots([['10:00', '12:00']], 60, step: 60))->toBe(['10:00', '11:00']);
    });
});

describe('пересечения с записями', function () {
    // Рабочий день 10:00–14:00, занято 11:00–12:00, новая услуга на час.
    beforeEach(fn () => $this->slots = slots([['10:00', '14:00']], 60, [busy('11:00', '12:00')]));

    it('пускает запись, которая заканчивается ровно в начале занятого', function () {
        expect($this->slots)->toContain('10:00');
    });

    it('не пускает запись, которая заезжает на занятое слева', function () {
        expect($this->slots)->not->toContain('10:15')->not->toContain('10:45');
    });

    it('не пускает запись внутри занятого и совпадающую с ним', function () {
        expect($this->slots)->not->toContain('11:00')->not->toContain('11:15');
    });

    it('не пускает запись, которая начинается до конца занятого', function () {
        expect($this->slots)->not->toContain('11:30')->not->toContain('11:45');
    });

    it('пускает запись сразу после занятого', function () {
        expect($this->slots)->toBe(['10:00', '12:00', '12:15', '12:30', '12:45', '13:00']);
    });

    it('не пускает запись, которая целиком накрывает короткое занятое', function () {
        expect(slots([['10:00', '12:00']], 60, [busy('10:20', '10:40')]))
            ->toBe(['10:45', '11:00']);
    });

    it('не даёт слотов, когда занят весь день', function () {
        expect(slots([['10:00', '14:00']], 30, [busy('09:00', '15:00')]))->toBe([]);
    });

    it('учитывает несколько записей подряд', function () {
        expect(slots([['10:00', '14:00']], 60, [busy('10:30', '11:30'), busy('12:30', '13:00')]))
            ->toBe(['11:30', '13:00']);
    });

    it('учитывает запись вчерашнего вечера, которая заходит на утро', function () {
        $late = new Interval(at('23:00', '2026-09-30'), at('00:30'));

        expect(slots([['00:00', '02:00']], 60, [$late], step: 30))
            ->toBe(['00:30', '01:00']);
    });
});

describe('перерывы', function () {
    it('не даёт записи задевать перерыв', function () {
        expect(slots([['10:00', '15:00']], 60, breaks: [['12:00', '13:00']], step: 30))
            ->toBe(['10:00', '10:30', '11:00', '13:00', '13:30', '14:00']);
    });

    it('учитывает несколько перерывов', function () {
        expect(slots([['10:00', '14:00']], 60, breaks: [['11:00', '11:30'], ['12:30', '13:00']], step: 30))
            ->toBe(['10:00', '11:30', '13:00']);
    });

    it('учитывает перерыв вместе с записями', function () {
        expect(slots([['10:00', '15:00']], 60, [busy('10:00', '11:00')], [['12:00', '13:00']], step: 60))
            ->toBe(['11:00', '13:00', '14:00']);
    });
});

describe('буфер после услуги', function () {
    it('не пускает запись, если буфер залезает на следующую запись', function () {
        // Услуга 45 минут + 15 минут уборки: 10:15 закончится с уборкой в 11:15.
        expect(slots([['10:00', '12:00']], 45, [busy('11:00', '12:00')], buffer: 15))
            ->toBe(['10:00']);
    });

    it('разрешает буферу выйти за конец рабочего дня', function () {
        expect(slots([['10:00', '12:00']], 60, buffer: 30, step: 60))->toBe(['10:00', '11:00']);
    });

    it('разрешает буферу совпасть с перерывом', function () {
        expect(slots([['10:00', '15:00']], 60, breaks: [['12:00', '13:00']], step: 60, buffer: 15))
            ->toBe(['10:00', '11:00', '13:00', '14:00']);
    });
});

describe('текущее время', function () {
    it('не показывает слоты раньше заданного момента', function () {
        expect(slots([['10:00', '12:00']], 60, notBefore: at('10:40')))
            ->toBe(['10:45', '11:00']);
    });

    it('показывает слот, который начинается ровно в заданный момент', function () {
        expect(slots([['10:00', '12:00']], 60, notBefore: at('11:00')))->toBe(['11:00']);
    });

    it('не показывает ничего, если день уже закончился', function () {
        expect(slots([['10:00', '12:00']], 60, notBefore: at('12:00')))->toBe([]);
    });
});

describe('часовые пояса', function () {
    it('возвращает слоты в UTC', function () {
        $day = new DaySchedule(DATE, 'Europe/Moscow', [['10:00', '11:00']]);

        $slot = (new SlotCalculator)->calculate($day, 60)[0];

        expect($slot->getTimezone()->getName())->toBe('UTC')
            ->and($slot->format('Y-m-d H:i'))->toBe('2026-10-01 07:00');
    });

    it('относит слоты к местной дате, даже если в UTC это ещё вчера', function () {
        // Владивосток, UTC+10: утро 1 октября — это вечер 30 сентября по UTC.
        $day = new DaySchedule(DATE, 'Asia/Vladivostok', [['08:00', '10:00']]);

        $slots = (new SlotCalculator)->calculate($day, 60, stepMinutes: 60);

        expect(array_map(fn ($slot) => $slot->format('Y-m-d H:i'), $slots))
            ->toBe(['2026-09-30 22:00', '2026-09-30 23:00']);
    });

    it('учитывает переход на летнее время: несуществующий час пропускается', function () {
        // Берлин, 29.03.2026: в 02:00 часы переводят на 03:00, смена 01:00–05:00 длится 3 часа.
        $day = new DaySchedule('2026-03-29', 'Europe/Berlin', [['01:00', '05:00']]);

        $slots = (new SlotCalculator)->calculate($day, 60, stepMinutes: 60);

        expect(array_map(fn ($slot) => $slot->setTimezone('Europe/Berlin')->format('H:i'), $slots))
            ->toBe(['01:00', '03:00', '04:00']);
    });

    it('учитывает переход на зимнее время: повторный час даёт лишний слот', function () {
        // Берлин, 25.10.2026: в 03:00 часы переводят на 02:00, смена 01:00–05:00 длится 5 часов.
        $day = new DaySchedule('2026-10-25', 'Europe/Berlin', [['01:00', '05:00']]);

        $slots = (new SlotCalculator)->calculate($day, 60, stepMinutes: 60);

        expect(array_map(fn ($slot) => $slot->setTimezone('Europe/Berlin')->format('H:i T'), $slots))
            ->toBe(['01:00 CEST', '02:00 CEST', '02:00 CET', '03:00 CET', '04:00 CET']);
    });
});

describe('некорректные данные', function () {
    it('не принимает рабочий интервал, где конец раньше начала', function () {
        slots([['19:00', '10:00']], 60);
    })->throws(InvalidArgumentException::class);

    it('не принимает нулевую длительность или шаг', function (int $duration, int $step) {
        slots([['10:00', '12:00']], $duration, step: $step);
    })->with([[0, 15], [60, 0]])->throws(InvalidArgumentException::class);
});
