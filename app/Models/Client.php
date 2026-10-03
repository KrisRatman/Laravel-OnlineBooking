<?php

namespace App\Models;

use App\Notifications\Channels\TelegramChannel;
use App\Support\Phone;
use Database\Factories\ClientFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

/**
 * Клиент студии. Пароля нет: в личный кабинет входит по одноразовому коду (guard «client»).
 */
#[Fillable(['name', 'phone', 'email', 'telegram_chat_id', 'notes'])]
#[Hidden(['remember_token'])]
class Client extends Model implements AuthenticatableContract
{
    /** @use HasFactory<ClientFactory> */
    use Authenticatable, HasFactory, Notifiable;

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasOne<ClientLoginCode, $this> */
    public function loginCode(): HasOne
    {
        return $this->hasOne(ClientLoginCode::class);
    }

    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegram_chat_id;
    }

    /**
     * Каналы уведомлений, которые клиент оставил: email и/или Telegram.
     *
     * @return list<string>
     */
    public function notificationChannels(): array
    {
        return array_values(array_filter([
            filled($this->email) ? 'mail' : null,
            filled($this->telegram_chat_id) ? TelegramChannel::class : null,
        ]));
    }

    /** Есть ли куда прислать код входа или уведомление. */
    public function hasContactChannel(): bool
    {
        return $this->notificationChannels() !== [];
    }

    public function formattedPhone(): string
    {
        return Phone::format($this->phone);
    }
}
