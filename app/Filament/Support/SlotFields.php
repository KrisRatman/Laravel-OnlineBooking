<?php

namespace App\Filament\Support;

use App\Models\Service;
use App\Models\Staff;
use App\Services\Slots\SlotService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Поля «услуга → мастер → дата → свободное время» для админки.
 * Время выбирается только из свободных слотов — те же правила, что на публичной странице.
 */
class SlotFields
{
    /** @return list<Grid> */
    public static function make(?int $ignoreAppointmentId = null, bool $serviceLocked = false): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('service_id')
                    ->label('Услуга')
                    ->options(fn () => Service::query()->active()->pluck('name', 'id'))
                    ->disabled($serviceLocked)
                    ->dehydrated()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('staff_id', null);
                        $set('slot', null);
                    }),
                Select::make('staff_id')
                    ->label('Мастер')
                    ->options(fn (Get $get) => self::service($get)
                        ? app(SlotService::class)->staffFor(self::service($get))->pluck('name', 'id')
                        : [])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('slot', null)),
                DatePicker::make('date')
                    ->label('Дата')
                    // Дата в поясе бизнеса, без перевода в UTC.
                    ->timezone(config('app.timezone'))
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->default(fn () => BusinessTime::today())
                    ->minDate(fn () => BusinessTime::today())
                    ->maxDate(fn () => last(app(SlotService::class)->bookableDates()))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('slot', null)),
                Select::make('slot')
                    ->label('Время')
                    ->options(fn (Get $get) => self::slotOptions($get, $ignoreAppointmentId))
                    ->placeholder(fn (Get $get) => $get('staff_id') && $get('date') && self::slotOptions($get, $ignoreAppointmentId) === []
                        ? 'Нет свободного времени'
                        : 'Выберите время')
                    ->required(),
            ]),
        ];
    }

    /** @return array<int, string> timestamp → «10:30» */
    private static function slotOptions(Get $get, ?int $ignoreAppointmentId): array
    {
        $service = self::service($get);
        $staff = $get('staff_id') ? Staff::find($get('staff_id')) : null;
        $date = $get('date');

        if (! $service || ! $staff || ! $date) {
            return [];
        }

        return collect(app(SlotService::class)->availableSlots($service, $staff, CarbonImmutable::parse($date)->toDateString(), ignoreAppointmentId: $ignoreAppointmentId))
            ->mapWithKeys(fn (CarbonImmutable $start) => [$start->getTimestamp() => BusinessTime::local($start)->format('H:i')])
            ->all();
    }

    private static function service(Get $get): ?Service
    {
        return $get('service_id') ? Service::find($get('service_id')) : null;
    }
}
