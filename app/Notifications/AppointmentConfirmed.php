<?php

namespace App\Notifications;

class AppointmentConfirmed extends AppointmentNotification
{
    protected function subject(): string
    {
        return 'Запись подтверждена';
    }

    protected function intro(): string
    {
        return 'Ваша запись подтверждена, ждём вас.';
    }
}
