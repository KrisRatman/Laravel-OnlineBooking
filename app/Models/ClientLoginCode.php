<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Действующий код входа в личный кабинет. У клиента не больше одного: новый код заменяет старый.
 *
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['client_id', 'code_hash', 'attempts', 'expires_at'])]
#[Hidden(['code_hash'])]
class ClientLoginCode extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
