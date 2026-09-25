<?php

use App\Services\Slots\Interval;
use Carbon\CarbonImmutable;

function interval(string $from, string $to): Interval
{
    return new Interval(CarbonImmutable::parse("2026-10-01 {$from}", 'UTC'), CarbonImmutable::parse("2026-10-01 {$to}", 'UTC'));
}

it('считает касание краями непересечением', function () {
    expect(interval('10:00', '11:00')->overlaps(interval('11:00', '12:00')))->toBeFalse()
        ->and(interval('11:00', '12:00')->overlaps(interval('10:00', '11:00')))->toBeFalse();
});

it('находит пересечения любого вида', function (string $from, string $to) {
    expect(interval('10:00', '12:00')->overlaps(interval($from, $to)))->toBeTrue()
        ->and(interval($from, $to)->overlaps(interval('10:00', '12:00')))->toBeTrue();
})->with([
    'слева' => ['09:00', '10:30'],
    'справа' => ['11:30', '13:00'],
    'внутри' => ['10:30', '11:00'],
    'накрывает' => ['09:00', '13:00'],
    'совпадает' => ['10:00', '12:00'],
]);

it('проверяет вложенность', function () {
    expect(interval('10:00', '12:00')->contains(interval('10:00', '12:00')))->toBeTrue()
        ->and(interval('10:00', '12:00')->contains(interval('10:30', '11:00')))->toBeTrue()
        ->and(interval('10:00', '12:00')->contains(interval('11:30', '12:30')))->toBeFalse();
});

it('не допускает пустой или перевёрнутый интервал', function (string $from, string $to) {
    interval($from, $to);
})->with([
    ['10:00', '10:00'],
    ['11:00', '10:00'],
])->throws(InvalidArgumentException::class);
