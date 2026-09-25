<?php

namespace App\Notifications;

class AppointmentBooked extends AppointmentNotification
{
    protected function subject(): string
    {
        return 'Вы записаны';
    }

    protected function intro(): string
    {
        return 'Мы получили вашу запись. Администратор подтвердит её в ближайшее время.';
    }
}
