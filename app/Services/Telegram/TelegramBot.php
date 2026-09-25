<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Минимальный клиент Bot API: боту нужно только отправлять сообщения
 * и принимать /start, поэтому отдельный SDK не подключаем.
 */
class TelegramBot
{
    public function isConfigured(): bool
    {
        return filled(config('booking.telegram.token'));
    }

    public function username(): ?string
    {
        return config('booking.telegram.username');
    }

    /** Ссылка, по которой клиент открывает бота и привязывает к себе чат. */
    public function startLink(string $payload): ?string
    {
        return $this->isConfigured() && filled($this->username())
            ? 'https://t.me/'.$this->username().'?start='.$payload
            : null;
    }

    public function sendMessage(string $chatId, string $html): Response
    {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $html,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    /** @param array<string, mixed> $params */
    public function call(string $method, array $params = []): Response
    {
        return Http::timeout(10)
            ->post('https://api.telegram.org/bot'.config('booking.telegram.token').'/'.$method, $params)
            ->throw();
    }
}
