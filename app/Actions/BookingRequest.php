<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

/**
 * Данные новой записи. Телефон уже нормализован, время — в UTC.
 */
final readonly class BookingRequest
{
    public function __construct(
        public int $serviceId,
        public ?int $staffId,
        public CarbonImmutable $startsAt,
        public string $name,
        public string $phone,
        public ?string $email = null,
        public ?string $comment = null,
        // Администратор записал клиента по телефону: можно сразу подтвердить, коллег не оповещать.
        public bool $confirmed = false,
        public bool $fromAdmin = false,
        // Клиент вошёл в личный кабинет: телефон его, контакты можно обновить.
        public bool $authenticated = false,
    ) {}
}
