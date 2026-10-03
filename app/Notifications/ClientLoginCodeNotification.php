<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Код входа в личный кабинет. Отправляется сразу, без очереди: клиент ждёт его на странице.
 */
class ClientLoginCodeNotification extends Notification
{
    public function __construct(
        public string $code,
    ) {}

    /** @return list<string> */
    public function via(Client $notifiable): array
    {
        return $notifiable->notificationChannels();
    }

    public function toMail(Client $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Код входа: {$this->code}")
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line("Код для входа в личный кабинет: **{$this->code}**")
            ->line($this->warning())
            ->salutation(config('app.name'));
    }

    public function toTelegram(Client $notifiable): string
    {
        return "Код для входа в личный кабинет: <b>{$this->code}</b>\n\n".e($this->warning());
    }

    private function warning(): string
    {
        return 'Код действует '.config('booking.login_code.ttl_minutes').' минут. '
            .'Никому его не сообщайте. Если вы не входили, просто проигнорируйте это сообщение.';
    }
}
