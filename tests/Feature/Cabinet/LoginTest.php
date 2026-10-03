<?php

use App\Livewire\Cabinet\Login;
use App\Models\Client;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\ClientLoginCodeNotification;
use App\Services\ClientAuth\LoginCodes;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));
    $this->client = Client::factory()->withTelegram('555')->create(['phone' => '+79161234567', 'email' => 'olga@example.com']);
});

/** Отправляет код через сервис и возвращает его из перехваченного уведомления. */
function requestLoginCode(Client $client): string
{
    app(LoginCodes::class)->send($client->phone);

    $code = null;
    Notification::assertSentTo($client, ClientLoginCodeNotification::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('отправляет шестизначный код в Telegram и на email клиента', function () {
    expect(app(LoginCodes::class)->send('+79161234567'))->toBeTrue();

    Notification::assertSentTo($this->client, ClientLoginCodeNotification::class, function ($notification, array $channels) {
        return preg_match('/^\d{6}$/', $notification->code) === 1
            && $channels === ['mail', TelegramChannel::class];
    });

    expect($this->client->loginCode)
        ->attempts->toBe(0)
        ->expires_at->toEqual(now()->addMinutes(10));
});

it('не отправляет код незнакомому номеру и клиенту без email и Telegram', function () {
    $noChannels = Client::factory()->withoutEmail()->create(['phone' => '+79160000000']);

    expect(app(LoginCodes::class)->send('+79169999999'))->toBeFalse()
        ->and(app(LoginCodes::class)->send($noChannels->phone))->toBeFalse();

    Notification::assertNothingSent();
});

it('пускает по верному коду только один раз', function () {
    $code = requestLoginCode($this->client);
    $codes = app(LoginCodes::class);

    expect($codes->verify($this->client->phone, $code)?->id)->toBe($this->client->id)
        ->and($codes->verify($this->client->phone, $code))->toBeNull();
});

it('новый код заменяет прежний', function () {
    $first = requestLoginCode($this->client);
    Notification::fake();
    $second = requestLoginCode($this->client);

    $codes = app(LoginCodes::class);

    expect($first === $second || $codes->verify($this->client->phone, $first) === null)->toBeTrue()
        ->and($codes->verify($this->client->phone, $second))->not->toBeNull();
});

it('не пускает по устаревшему коду', function () {
    $code = requestLoginCode($this->client);

    $this->travel(11)->minutes();

    expect(app(LoginCodes::class)->verify($this->client->phone, $code))->toBeNull();
});

it('после пяти неверных попыток не принимает даже верный код', function () {
    $code = requestLoginCode($this->client);
    $wrong = $code === '000000' ? '111111' : '000000';
    $codes = app(LoginCodes::class);

    foreach (range(1, 5) as $_) {
        expect($codes->verify($this->client->phone, $wrong))->toBeNull();
    }

    expect($codes->verify($this->client->phone, $code))->toBeNull();
});

it('код одного клиента не подходит другому', function () {
    $other = Client::factory()->create();
    $code = requestLoginCode($this->client);

    expect(app(LoginCodes::class)->verify($other->phone, $code))->toBeNull();
});

it('входит в кабинет по телефону в любом формате и коду', function () {
    $component = Livewire::test(Login::class)
        ->set('phone', '8 (916) 123-45-67')
        ->call('sendCode')
        ->assertSet('codeSentTo', '+79161234567')
        ->assertSee('+7 (916) 123-45-67');

    $code = null;
    Notification::assertSentTo($this->client, ClientLoginCodeNotification::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    $component->set('code', $code)->call('verify')->assertRedirect(route('cabinet'));

    expect(auth('client')->id())->toBe($this->client->id)
        ->and(auth('web')->check())->toBeFalse();
});

it('отвечает одинаково на любой номер, чтобы нельзя было узнать клиентов студии', function () {
    Livewire::test(Login::class)
        ->set('phone', '+7 999 000-00-00')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('codeSentTo', '+79990000000')
        ->set('code', '123456')
        ->call('verify')
        ->assertHasErrors('code');

    expect(auth('client')->check())->toBeFalse();
});

it('проверяет формат телефона и кода', function () {
    Livewire::test(Login::class)
        ->set('phone', '123')
        ->call('sendCode')
        ->assertHasErrors('phone')
        ->set('phone', '+79161234567')
        ->call('sendCode')
        ->set('code', '12ab')
        ->call('verify')
        ->assertHasErrors(['code' => 'digits']);
});

it('ограничивает число кодов на один номер', function () {
    $component = Livewire::test(Login::class)->set('phone', '+79161234567');

    foreach (range(1, 3) as $_) {
        $component->call('sendCode')->assertHasNoErrors();
    }

    $component->call('sendCode')->assertHasErrors('phone');
    Notification::assertSentToTimes($this->client, ClientLoginCodeNotification::class, 3);
});

it('пускает демо-клиента без кода только в демо-режиме', function () {
    $demo = Client::factory()->create(['phone' => config('booking.demo.client_phone')]);

    Livewire::test(Login::class)->assertDontSee('Войти как демо-клиент')->call('loginAsDemo')->assertNotFound();
    expect(auth('client')->check())->toBeFalse();

    config(['booking.demo.enabled' => true]);

    Livewire::test(Login::class)
        ->assertSee('Войти как демо-клиент')
        ->call('loginAsDemo')
        ->assertRedirect(route('cabinet'));

    expect(auth('client')->id())->toBe($demo->id);
});

it('отправляет гостя из кабинета на вход, а вошедшего — со входа в кабинет', function () {
    $this->get(route('cabinet'))->assertRedirect(route('cabinet.login'));
    $this->get(route('cabinet.login'))->assertOk()->assertSee('Получить код');

    $this->actingAs($this->client, 'client');

    $this->get(route('cabinet.login'))->assertRedirect(route('cabinet'));
});

it('выходит из кабинета', function () {
    $this->actingAs($this->client, 'client')
        ->post(route('cabinet.logout'))
        ->assertRedirect(route('home'));

    expect(auth('client')->check())->toBeFalse();
});
