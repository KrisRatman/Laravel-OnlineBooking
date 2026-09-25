<?php

namespace App\Enums;

enum ReminderKind: string
{
    // За сутки.
    case Day = 'day';

    // За 2 часа.
    case Hours = 'hours';

    /** За сколько минут до начала отправлять. */
    public function minutesBefore(): int
    {
        return config('booking.reminders.'.$this->value);
    }

    /** Колонка appointments с отметкой об отправке. */
    public function sentAtColumn(): string
    {
        return "reminder_{$this->value}_sent_at";
    }
}
