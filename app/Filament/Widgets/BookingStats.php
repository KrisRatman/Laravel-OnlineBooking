<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$todayFrom, $todayTo] = BusinessTime::dayBounds(BusinessTime::today());
        $weekStart = CarbonImmutable::parse(BusinessTime::today())->startOfWeek();
        [$weekFrom] = BusinessTime::dayBounds($weekStart->toDateString());
        [$weekTo] = BusinessTime::dayBounds($weekStart->addWeek()->toDateString());

        $today = Appointment::query()->active()->where('starts_at', '>=', $todayFrom)->where('starts_at', '<', $todayTo);
        $pending = Appointment::query()->where('status', AppointmentStatus::New)->where('starts_at', '>', now())->count();
        $weekRevenue = Appointment::query()
            ->whereIn('status', [...AppointmentStatus::active(), AppointmentStatus::Completed])
            ->where('starts_at', '>=', $weekFrom)
            ->where('starts_at', '<', $weekTo)
            ->sum('price');
        $newClients = Client::query()->where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Записей сегодня', (clone $today)->count())
                ->description('Выручка дня: '.money_rub((int) (clone $today)->sum('price')))
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Ждут подтверждения', $pending)
                ->description($pending ? 'Подтвердите, чтобы клиент получил уведомление' : 'Все записи подтверждены')
                ->color($pending ? 'warning' : 'success')
                ->icon('heroicon-o-bell-alert'),
            Stat::make('Выручка недели', money_rub((int) $weekRevenue))
                ->description('Выполненные и предстоящие')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Новых клиентов', $newClients)
                ->description('за 30 дней')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
