<?php

namespace App\Filament\Resources\Staff\RelationManagers;

use App\Enums\ScheduleExceptionType;
use App\Filament\Support\LocalTimePicker;
use App\Models\ScheduleException;
use App\Support\BusinessTime;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * Разовые изменения графика: отпуск, больничный, короткий день, выход в выходной.
 */
class ScheduleExceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleExceptions';

    protected static ?string $title = 'Исключения из графика';

    protected static ?string $modelLabel = 'исключение';

    protected static ?string $pluralModelLabel = 'исключения';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('date')
                    ->label('Дата')
                    // Календарная дата бизнеса: без перевода в UTC.
                    ->timezone(config('app.timezone'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('staff_id', $this->getOwnerRecord()->getKey()))
                    ->validationMessages(['unique' => 'На эту дату у мастера уже есть исключение.']),
                ToggleButtons::make('type')
                    ->label('Что меняется')
                    ->options(ScheduleExceptionType::class)
                    ->default(ScheduleExceptionType::DayOff)
                    ->inline()
                    ->live()
                    ->required(),
                LocalTimePicker::make('starts_at')
                    ->label('Работает с')
                    ->visible(fn (Get $get) => $this->isCustomHours($get('type')))
                    ->required(),
                LocalTimePicker::make('ends_at')
                    ->label('до')
                    ->visible(fn (Get $get) => $this->isCustomHours($get('type')))
                    ->required()
                    ->after('starts_at'),
                TextInput::make('note')
                    ->label('Комментарий')
                    ->placeholder('Отпуск')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y, D', timezone: config('app.timezone')),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge(),
                TextColumn::make('hours')
                    ->label('Часы')
                    ->state(fn (ScheduleException $record) => $record->type === ScheduleExceptionType::CustomHours
                        ? substr($record->starts_at, 0, 5).'–'.substr($record->ends_at, 0, 5)
                        : '—'),
                TextColumn::make('note')->label('Комментарий'),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Только будущие')
                    ->default()
                    ->query(fn ($query) => $query->where('date', '>=', BusinessTime::today())),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить исключение'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private function isCustomHours(mixed $type): bool
    {
        return ($type instanceof ScheduleExceptionType ? $type : ScheduleExceptionType::tryFrom((string) $type)) === ScheduleExceptionType::CustomHours;
    }
}
