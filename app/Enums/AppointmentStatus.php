<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::Confirmed => 'Подтверждена',
            self::Cancelled => 'Отменена',
            self::Completed => 'Выполнена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
            self::Completed => 'gray',
        };
    }

    /** Цвет события в календаре админки. */
    public function hex(): string
    {
        return match ($this) {
            self::New => '#f59e0b',
            self::Confirmed => '#10b981',
            self::Cancelled => '#ef4444',
            self::Completed => '#6b7280',
        };
    }

    /**
     * Статусы, при которых запись занимает время мастера.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::New, self::Confirmed];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }
}
