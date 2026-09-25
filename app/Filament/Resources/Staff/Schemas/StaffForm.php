<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Filament\Support\LocalTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public const WEEKDAYS = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
        7 => 'Воскресенье',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label('Должность')
                            ->placeholder('Парикмахер-стилист')
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->label('О мастере')
                            ->rows(3)
                            ->columnSpanFull(),
                        FileUpload::make('photo')
                            ->label('Фото')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('staff'),
                        Grid::make(1)->schema([
                            TextInput::make('sort')
                                ->label('Порядок')
                                ->numeric()
                                ->integer()
                                ->default(0),
                            Toggle::make('is_active')
                                ->label('Принимает записи')
                                ->default(true),
                        ]),
                    ]),

                Section::make('График работы')
                    ->description('Время указывается по часовому поясу студии. Разовые изменения (отпуск, короткий день) — в разделе «Исключения» ниже.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        self::scheduleRepeater('workingHours', 'Рабочие часы', 'Добавить рабочий день'),
                        self::scheduleRepeater('breaks', 'Перерывы', 'Добавить перерыв'),
                    ]),
            ]);
    }

    private static function scheduleRepeater(string $relationship, string $label, string $addLabel): Repeater
    {
        return Repeater::make($relationship)
            ->label($label)
            ->relationship()
            ->orderColumn(false)
            ->defaultItems(0)
            ->addActionLabel($addLabel)
            ->columns(3)
            ->itemLabel(fn (array $state) => self::WEEKDAYS[$state['weekday'] ?? 0] ?? null)
            ->collapsible()
            ->schema([
                Select::make('weekday')
                    ->label('День')
                    ->options(self::WEEKDAYS)
                    ->required(),
                LocalTimePicker::make('starts_at')
                    ->label('С')
                    ->required(),
                LocalTimePicker::make('ends_at')
                    ->label('До')
                    ->required()
                    ->after('starts_at'),
            ]);
    }
}
