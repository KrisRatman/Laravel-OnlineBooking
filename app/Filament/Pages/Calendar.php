<?php

namespace App\Filament\Pages;

use App\Filament\Calendar\AppointmentCalendarWidget;
use App\Filament\Resources\Appointments\AppointmentResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Записи';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Календарь';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Новая запись')
                ->icon(Heroicon::OutlinedPlus)
                ->url(AppointmentResource::getUrl('create')),
        ];
    }

    public function getCalendarWidget(): string
    {
        return AppointmentCalendarWidget::class;
    }
}
