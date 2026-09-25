<?php

namespace App\Notifications\Channels;

use App\Services\Telegram\TelegramBot;
use Illuminate\Notifications\Notification;

class TelegramChannel
{
    public function __construct(
        private readonly TelegramBot $bot,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if (blank($chatId) || ! $this->bot->isConfigured() || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $this->bot->sendMessage((string) $chatId, $notification->toTelegram($notifiable));
    }
}
