<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScheduleExceptionType: string implements HasColor, HasLabel
{
    // Мастер не работает весь день: отпуск, больничный.
    case DayOff = 'day_off';

    // Особые часы: сокращённый день или выход в обычный выходной.
    case CustomHours = 'custom_hours';

    public function getLabel(): string
    {
        return match ($this) {
            self::DayOff => 'Выходной',
            self::CustomHours => 'Особые часы',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DayOff => 'danger',
            self::CustomHours => 'info',
        };
    }
}
