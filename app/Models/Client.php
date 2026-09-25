<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'phone', 'email', 'telegram_chat_id', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegram_chat_id;
    }

    /** +79991234567 → +7 (999) 123-45-67 */
    public function formattedPhone(): string
    {
        if (! preg_match('/^\+7(\d{3})(\d{3})(\d{2})(\d{2})$/', $this->phone, $m)) {
            return $this->phone;
        }

        return "+7 ({$m[1]}) {$m[2]}-{$m[3]}-{$m[4]}";
    }
}
