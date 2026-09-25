<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TimePicker;

/**
 * Время расписания («10:00») — это местное время бизнеса без даты.
 *
 * Панель работает в поясе бизнеса (FilamentTimezone) и переводит время в UTC приложения.
 * Для расписания перевод не нужен: пояс поля совпадает с поясом приложения, и значение
 * сохраняется ровно так, как его ввели.
 */
class LocalTimePicker
{
    public static function make(string $name): TimePicker
    {
        return TimePicker::make($name)
            ->timezone(config('app.timezone'))
            ->seconds(false)
            ->minutesStep(5)
            ->native(false)
            ->displayFormat('H:i');
    }
}
