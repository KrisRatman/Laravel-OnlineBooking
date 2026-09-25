<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/**
 * Поле цены: администратор вводит рубли, в БД хранятся копейки.
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->step(0.01)
            ->suffix('₽')
            ->formatStateUsing(fn (?int $state) => $state === null ? null : $state / 100)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round((float) $state * 100) : null);
    }
}
