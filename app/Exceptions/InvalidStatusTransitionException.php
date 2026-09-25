<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(AppointmentStatus $from, AppointmentStatus $to)
    {
        parent::__construct("Нельзя перевести запись из статуса «{$from->getLabel()}» в «{$to->getLabel()}».");
    }
}
