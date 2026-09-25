<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Выбранное время уже занято или стало недоступным (например, его забрал другой клиент).
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Это время уже занято. Выберите, пожалуйста, другое.')
    {
        parent::__construct($message);
    }
}
