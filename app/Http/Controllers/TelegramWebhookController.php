<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Бот нужен только для напоминаний. После записи клиент открывает ссылку
 * t.me/<бот>?start=<токен записи>, Telegram присылает «/start <токен>»,
 * и мы запоминаем chat_id этого клиента.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot): Response
    {
        $secret = (string) config('booking.telegram.webhook_secret');

        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text'));

        if ($chatId === null) {
            // Не сообщение (редактирование, callback и т.п.) — просто подтверждаем получение.
            return response()->noContent();
        }

        try {
            $bot->sendMessage((string) $chatId, $this->reply((string) $chatId, $text));
        } catch (Throwable $e) {
            // Telegram повторяет webhook при ошибке — не зацикливаемся на недоступном API.
            Log::warning('Не удалось ответить в Telegram', ['error' => $e->getMessage()]);
        }

        return response()->noContent();
    }

    private function reply(string $chatId, string $text): string
    {
        if (! preg_match('/^\/start\s+([A-Za-z0-9]{40})$/', $text, $matches)) {
            return 'Здравствуйте! Этот бот присылает напоминания о записи. '
                .'Чтобы подключить их, нажмите кнопку «Напоминания в Telegram» на странице вашей записи.';
        }

        $appointment = Appointment::query()->where('token', $matches[1])->with(['client', 'service'])->first();

        if ($appointment === null) {
            return 'Запись не найдена. Откройте ссылку со страницы записи ещё раз.';
        }

        $client = $appointment->client;

        // Через Telegram приходят коды входа в кабинет: чужой чат не заменяет уже привязанный.
        if (filled($client->telegram_chat_id) && $client->telegram_chat_id !== $chatId) {
            return 'К этому номеру телефона уже подключён другой Telegram. '
                .'Отключите его в личном кабинете или обратитесь в студию.';
        }

        $client->update(['telegram_chat_id' => $chatId]);

        $when = $appointment->localStartsAt()->locale('ru')->isoFormat('D MMMM, HH:mm');

        return "Готово, {$this->escape($appointment->client->name)}! Напомним о записи на "
            ."<b>{$this->escape($appointment->service->name)}</b> ({$when}) за сутки и за 2 часа.";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
    }
}
