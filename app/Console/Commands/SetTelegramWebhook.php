<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('booking:telegram-webhook {--delete : Отключить webhook}')]
#[Description('Подключить webhook бота напоминаний к адресу APP_URL/telegram/webhook')]
class SetTelegramWebhook extends Command
{
    public function handle(TelegramBot $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('Не задан TELEGRAM_BOT_TOKEN.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $bot->call('deleteWebhook');
            $this->info('Webhook отключён.');

            return self::SUCCESS;
        }

        if (blank(config('booking.telegram.webhook_secret'))) {
            $this->error('Не задан TELEGRAM_WEBHOOK_SECRET.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        $bot->call('setWebhook', [
            'url' => $url,
            'secret_token' => config('booking.telegram.webhook_secret'),
            'allowed_updates' => ['message'],
        ]);

        $this->info("Webhook подключён: {$url}");

        return self::SUCCESS;
    }
}
