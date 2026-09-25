<?php

namespace App\Notifications\Admin;

class AppointmentCancelledByClient extends AdminAppointmentNotification
{
    protected function title(): string
    {
        return 'Клиент отменил запись';
    }

    protected function icon(): string
    {
        return 'heroicon-o-x-circle';
    }
}
