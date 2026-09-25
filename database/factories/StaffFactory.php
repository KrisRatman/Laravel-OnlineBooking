<?php

namespace Database\Factories;

use App\Models\ScheduleBreak;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => 'Мастер',
            'bio' => null,
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Одинаковые часы на указанные дни недели (ISO: 1 — пн).
     *
     * @param  list<int>  $weekdays
     */
    public function worksOn(array $weekdays = [1, 2, 3, 4, 5, 6, 7], string $from = '10:00', string $to = '19:00'): static
    {
        return $this->afterCreating(function (Staff $staff) use ($weekdays, $from, $to) {
            foreach ($weekdays as $weekday) {
                WorkingHour::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);
            }
        });
    }

    /** @param list<int> $weekdays */
    public function withBreak(string $from, string $to, array $weekdays = [1, 2, 3, 4, 5, 6, 7]): static
    {
        return $this->afterCreating(function (Staff $staff) use ($weekdays, $from, $to) {
            foreach ($weekdays as $weekday) {
                ScheduleBreak::create(['staff_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);
            }
        });
    }

    /** @param array{price?: int, duration_minutes?: int} $pivot */
    public function offers(Service $service, array $pivot = []): static
    {
        return $this->afterCreating(fn (Staff $staff) => $staff->services()->attach($service, $pivot));
    }
}
