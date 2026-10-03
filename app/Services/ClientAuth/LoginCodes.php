<?php

namespace App\Services\ClientAuth;

use App\Models\Client;
use App\Models\ClientLoginCode;
use App\Notifications\ClientLoginCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Вход в личный кабинет без пароля: шестизначный код в Telegram или на email.
 *
 * Телефон не подтверждён (SMS нет), поэтому код уходит только в каналы,
 * которые уже привязаны к клиенту. Номер, к которому нечего прислать, ведёт себя
 * для посетителя так же, как незнакомый: по ответу нельзя узнать, кто клиент студии.
 */
class LoginCodes
{
    /** Создаёт новый код вместо прежнего и отправляет его. false — отправлять некуда. */
    public function send(string $phone): bool
    {
        $client = Client::query()->where('phone', $phone)->first();

        if ($client === null || ! $client->hasContactChannel()) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $client->loginCode()->updateOrCreate([], [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(config('booking.login_code.ttl_minutes')),
        ]);

        $client->notify(new ClientLoginCodeNotification($code));

        return true;
    }

    /** Клиент, если код верный. Код одноразовый, на неверные попытки есть лимит. */
    public function verify(string $phone, string $code): ?Client
    {
        $client = Client::query()->where('phone', $phone)->with('loginCode')->first();
        $loginCode = $client?->loginCode;

        if ($loginCode === null || $loginCode->expires_at->isPast()) {
            return null;
        }

        // Попытку засчитываем до проверки и одним запросом: параллельные запросы не обойдут лимит.
        $counted = ClientLoginCode::query()
            ->whereKey($loginCode->id)
            ->where('attempts', '<', config('booking.login_code.max_attempts'))
            ->increment('attempts');

        if ($counted === 0 || ! Hash::check($code, $loginCode->code_hash)) {
            return null;
        }

        $loginCode->delete();

        return $client;
    }
}
