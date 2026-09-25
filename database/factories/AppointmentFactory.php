<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

        return [
            'client_id' => Client::factory(),
            'staff_id' => Staff::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'price' => 150000,
            'buffer_minutes' => 0,
            'status' => AppointmentStatus::New,
        ];
    }

    /** Начало в UTC и длительность в минутах. */
    public function at(CarbonImmutable $start, int $minutes = 60): static
    {
        return $this->state([
            'starts_at' => $start->utc(),
            'ends_at' => $start->utc()->addMinutes($minutes),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : null,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }
}
