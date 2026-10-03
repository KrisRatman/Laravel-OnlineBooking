<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->travelTo(moscow('09:00', '2026-09-30'));
});

it('создаёт демо-студию с записями без пересечений', function () {
    $this->seed(DemoSeeder::class);

    expect(User::where('email', 'admin@example.com')->exists())->toBeTrue()
        ->and(Appointment::count())->toBeGreaterThan(100)
        ->and(Appointment::where('starts_at', '>', now())->active()->count())->toBeGreaterThan(20);

    // Ни у одного мастера активные записи (с буфером) не пересекаются.
    Appointment::query()
        ->whereIn('status', [...AppointmentStatus::active(), AppointmentStatus::Completed])
        ->orderBy('starts_at')
        ->get()
        ->groupBy('staff_id')
        ->each(function ($appointments) {
            $appointments->sliding(2)->each(function ($pair) {
                [$previous, $next] = $pair->values();

                expect($next->starts_at->greaterThanOrEqualTo($previous->blockedUntil()))
                    ->toBeTrue("Пересечение записей #{$previous->id} и #{$next->id}");
            });
        });
});

it('даёт демо-клиенту историю визитов и записи, которые можно перенести', function () {
    $this->seed(DemoSeeder::class);

    $demo = Client::where('phone', config('booking.demo.client_phone'))->sole();

    expect($demo->email)->not->toBeNull()
        ->and($demo->appointments()->where('status', AppointmentStatus::Completed)->count())->toBeGreaterThanOrEqual(3)
        ->and($demo->appointments()->active()->get())
        ->filter(fn (Appointment $appointment) => $appointment->canBeRescheduledByClient())
        ->count()->toBeGreaterThanOrEqual(2);
});

it('повторный запуск ничего не дублирует', function () {
    $this->seed(DemoSeeder::class);
    $count = Appointment::count();

    $this->seed(DemoSeeder::class);

    expect(Appointment::count())->toBe($count);
});
