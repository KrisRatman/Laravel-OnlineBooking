<?php

namespace App\Filament\Resources\Staff\Tables;

use App\Filament\Resources\Staff\Schemas\StaffForm;
use App\Models\Staff;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->modifyQueryUsing(fn ($query) => $query->with('workingHours')->withCount('services'))
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->description(fn (Staff $record) => $record->position)
                    ->searchable(),
                TextColumn::make('schedule')
                    ->label('Рабочие дни')
                    ->state(fn (Staff $record) => $record->workingHours
                        ->unique('weekday')
                        ->map(fn ($row) => mb_substr(StaffForm::WEEKDAYS[$row->weekday], 0, 2))
                        ->implode(', ') ?: '—'),
                TextColumn::make('services_count')
                    ->label('Услуг'),
                ToggleColumn::make('is_active')
                    ->label('Принимает записи'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
