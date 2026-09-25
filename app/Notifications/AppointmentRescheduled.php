<?php

namespace App\Notifications;

class AppointmentRescheduled extends AppointmentNotification
{
    protected function subject(): string
    {
        return 'Запись перенесена';
    }

    protected function intro(): string
    {
        return 'Время вашей записи изменилось. Новые условия:';
    }
}
