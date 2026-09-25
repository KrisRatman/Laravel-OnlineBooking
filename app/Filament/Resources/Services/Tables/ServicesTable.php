<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->description(fn (Service $record) => str($record->description)->limit(60))
                    ->searchable(),
                TextColumn::make('duration_minutes')
                    ->label('Длительность')
                    ->formatStateUsing(fn (Service $record) => $record->duration_minutes.' мин'
                        .($record->buffer_minutes ? " + {$record->buffer_minutes}" : ''))
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn (int $state) => money_rub($state))
                    ->sortable(),
                TextColumn::make('staff_count')
                    ->label('Мастеров')
                    ->counts('staff'),
                ToggleColumn::make('is_active')
                    ->label('Активна'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Активна'),
            ])
            ->recordActions([
                EditAction::make(),
                // Услугу с записями удалять нельзя (история и отчёты) — её можно только скрыть.
                DeleteAction::make()
                    ->hidden(fn (Service $record) => $record->appointments()->exists()),
            ]);
    }
}
