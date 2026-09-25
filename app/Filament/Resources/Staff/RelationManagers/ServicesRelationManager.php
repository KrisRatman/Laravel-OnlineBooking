<?php

namespace App\Filament\Resources\Staff\RelationManagers;

use App\Filament\Support\MoneyInput;
use App\Models\Service;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Какие услуги делает мастер и на каких условиях (своя цена или длительность).
 */
class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $title = 'Услуги мастера';

    protected static ?string $modelLabel = 'услуга';

    protected static ?string $pluralModelLabel = 'услуги';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Услуга'),
                TextColumn::make('duration')
                    ->label('Длительность')
                    ->state(fn (Service $record) => ($record->pivot->duration_minutes ?? $record->duration_minutes).' мин')
                    ->description(fn (Service $record) => $record->pivot->duration_minutes ? 'своя' : null),
                TextColumn::make('final_price')
                    ->label('Цена')
                    ->state(fn (Service $record) => money_rub($record->pivot->price ?? $record->price))
                    ->description(fn (Service $record) => $record->pivot->price ? 'своя' : null),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Добавить услугу')
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect()->label('Услуга'),
                        ...self::pivotFields(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Условия'),
                DetachAction::make()->label('Убрать'),
            ]);
    }

    /** @return list<Field> */
    private static function pivotFields(): array
    {
        return [
            MoneyInput::make('price')
                ->label('Своя цена')
                ->helperText('Пусто — цена из карточки услуги.'),
            TextInput::make('duration_minutes')
                ->label('Своя длительность')
                ->helperText('Пусто — длительность из карточки услуги.')
                ->numeric()
                ->integer()
                ->minValue(5)
                ->step(5)
                ->suffix('мин'),
        ];
    }
}
