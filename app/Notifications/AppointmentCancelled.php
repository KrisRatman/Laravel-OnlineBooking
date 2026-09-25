<?php

namespace App\Notifications;

use App\Models\Appointment;

class AppointmentCancelled extends AppointmentNotification
{
    public function __construct(
        Appointment $appointment,
        public bool $byClient = false,
    ) {
        parent::__construct($appointment);
    }

    protected function subject(): string
    {
        return 'Запись отменена';
    }

    protected function intro(): string
    {
        if ($this->byClient) {
            return 'Вы отменили запись. Будем рады видеть вас в другой раз.';
        }

        $reason = $this->appointment->cancel_reason;

        return 'К сожалению, запись отменена'.($reason ? ": {$reason}" : '.').' Запишитесь, пожалуйста, на другое время.';
    }
}
