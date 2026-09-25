<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature-тесты работают с приложением и базой. Unit-тесты — чистый PHP
| без фреймворка: так расчёт слотов проверяется быстро и изолированно.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Хелперы
|--------------------------------------------------------------------------
*/

/** Московское время (пояс демо-бизнеса) → момент в UTC, как он хранится в БД. */
function moscow(string $time, string $date = '2026-10-01'): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$time}", 'Europe/Moscow')->utc();
}
