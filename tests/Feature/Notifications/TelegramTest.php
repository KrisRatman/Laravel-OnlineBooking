<?php

use App\Models\Appointment;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $this->travelTo(moscow('09:00', '2026-09-30'));
    $this->appointment = Appointment::factory()->at(moscow('11:00'))->create();
});

function webhook(string $text, int $chatId = 555, string $secret = 'test-secret')
{
    return test()->postJson(route('telegram.webhook'), [
        'update_id' => 1,
        'message' => ['message_id' => 1, 'chat' => ['id' => $chatId, 'type' => 'private'], 'text' => $text],
    ], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
}

it('привязывает чат клиента по ссылке со страницы записи', function () {
    webhook('/start '.$this->appointment->token)->assertNoContent();

    expect($this->appointment->client->fresh()->telegram_chat_id)->toBe('555');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/bottest-token/sendMessage')
        && $request['chat_id'] === '555'
        && str_contains($request['text'], 'Напомним о записи'));
});

it('отвечает подсказкой на /start без токена и на неизвестный токен', function (string $text, string $reply) {
    webhook($text)->assertNoContent();

    expect($this->appointment->client->fresh()->telegram_chat_id)->toBeNull();
    Http::assertSent(fn (Request $request) => str_contains($request['text'], $reply));
})->with([
    ['/start', 'нажмите кнопку'],
    ['/start '.str_repeat('x', 40), 'Запись не найдена'],
    ['привет', 'нажмите кнопку'],
]);

it('отклоняет запросы без секрета Telegram', function () {
    webhook('/start '.$this->appointment->token, secret: 'wrong')->assertForbidden();

    expect($this->appointment->client->fresh()->telegram_chat_id)->toBeNull();
    Http::assertNothingSent();
});

it('отправляет уведомление в Telegram, если чат подключён', function () {
    $this->appointment->client->update(['telegram_chat_id' => '777', 'email' => null]);

    $this->appointment->client->notify(new AppointmentConfirmed($this->appointment));

    Http::assertSent(fn (Request $request) => $request['chat_id'] === '777'
        && $request['parse_mode'] === 'HTML'
        && str_contains($request['text'], 'Запись подтверждена')
        && str_contains($request['text'], '1 октября (чт), 11:00'));
});

it('не отправляет в Telegram без токена бота', function () {
    config(['booking.telegram.token' => null]);
    $this->appointment->client->update(['telegram_chat_id' => '777', 'email' => null]);

    $this->appointment->client->notify(new AppointmentConfirmed($this->appointment));

    Http::assertNothingSent();
});

it('не заменяет уже привязанный чат клиента чужим', function () {
    $this->appointment->client->update(['telegram_chat_id' => '777']);

    webhook('/start '.$this->appointment->token, chatId: 555)->assertNoContent();

    expect($this->appointment->client->fresh()->telegram_chat_id)->toBe('777');
    Http::assertSent(fn (Request $request) => $request['chat_id'] === '555'
        && str_contains($request['text'], 'уже подключён другой Telegram'));
});

it('повторный /start из того же чата проходит', function () {
    $this->appointment->client->update(['telegram_chat_id' => '555']);

    webhook('/start '.$this->appointment->token, chatId: 555)->assertNoContent();

    Http::assertSent(fn (Request $request) => str_contains($request['text'], 'Напомним о записи'));
});
