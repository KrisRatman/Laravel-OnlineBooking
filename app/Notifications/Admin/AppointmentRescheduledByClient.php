<?php

namespace App\Notifications\Admin;

class AppointmentRescheduledByClient extends AdminAppointmentNotification
{
    protected function title(): string
    {
        return 'Клиент перенёс запись';
    }

    protected function icon(): string
    {
        return 'heroicon-o-arrow-path';
    }
}
