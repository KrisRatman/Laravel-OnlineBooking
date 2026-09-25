<?php

namespace App\Notifications;

use App\Enums\ReminderKind;
use App\Models\Appointment;

class AppointmentReminder extends AppointmentNotification
{
    public function __construct(
        Appointment $appointment,
        public ReminderKind $kind,
    ) {
        parent::__construct($appointment);
    }

    protected function subject(): string
    {
        return $this->kind === ReminderKind::Day
            ? 'Напоминание: завтра у вас запись'
            : 'Напоминание: скоро ваша запись';
    }

    protected function intro(): string
    {
        return $this->kind === ReminderKind::Day
            ? 'Напоминаем о вашей записи. Если планы изменились, отмените её по ссылке ниже — это освободит время для других.'
            : 'Ждём вас через пару часов.';
    }
}
