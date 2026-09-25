<?php

namespace App\Notifications\Admin;

class NewAppointmentForAdmin extends AdminAppointmentNotification
{
    protected function title(): string
    {
        return 'Новая запись';
    }

    protected function icon(): string
    {
        return 'heroicon-o-calendar-days';
    }
}
