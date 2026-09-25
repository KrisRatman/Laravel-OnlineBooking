<?php

namespace App\Services\Slots;

use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Свободные слоты из данных БД: собирает расписание дня и занятое время
 * и передаёт их в {@see SlotCalculator}.
 */
class SlotService
{
    public function __construct(
        private readonly SlotCalculator $calculator,
    ) {}

    /**
     * Свободные начала записи к мастеру на дату (в поясе бизнеса).
     *
     * @param  int|null  $ignoreAppointmentId  не считать занятым время этой записи (перенос в админке)
     * @return list<CarbonImmutable> UTC
     */
    public function availableSlots(
        Service $service,
        Staff $staff,
        string $date,
        ?CarbonInterface $now = null,
        ?int $ignoreAppointmentId = null,
    ): array {
        $offer = $staff->is_active && $service->is_active ? $staff->offer($service) : null;

        if ($offer === null || ! $this->isBookableDate($date, $now)) {
            return [];
        }

        $day = $this->daySchedule($staff, $date);

        if ($day->isDayOff()) {
            return [];
        }

        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        return $this->calculator->calculate(
            day: $day,
            durationMinutes: $offer->durationMinutes,
            busy: $this->busyIntervals($staff, $date, $ignoreAppointmentId),
            stepMinutes: config('booking.slot_step'),
            bufferMinutes: $offer->bufferMinutes,
            notBefore: $now->addMinutes(config('booking.min_lead_minutes')),
        );
    }

    /**
     * Слоты «к любому мастеру»: время → мастера, свободные в это время.
     *
     * @return array<int, array{start: CarbonImmutable, staff: Collection<int, Staff>}> ключ — unix timestamp
     */
    public function availableSlotsForAnyStaff(Service $service, string $date, ?CarbonInterface $now = null): array
    {
        $result = [];

        foreach ($this->staffFor($service) as $staff) {
            foreach ($this->availableSlots($service, $staff, $date, $now) as $start) {
                $result[$start->getTimestamp()] ??= ['start' => $start, 'staff' => collect()];
                $result[$start->getTimestamp()]['staff']->push($staff);
            }
        }

        ksort($result);

        return $result;
    }

    public function isAvailable(
        Service $service,
        Staff $staff,
        CarbonInterface $startsAt,
        ?CarbonInterface $now = null,
        ?int $ignoreAppointmentId = null,
    ): bool {
        $date = BusinessTime::local($startsAt)->toDateString();

        foreach ($this->availableSlots($service, $staff, $date, $now, $ignoreAppointmentId) as $slot) {
            if ($slot->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Из свободных в это время мастеров выбирает наименее загруженного в этот день.
     *
     * @param  Collection<int, Staff>  $candidates
     */
    public function leastBusyStaff(Collection $candidates, string $date): ?Staff
    {
        [$from, $to] = BusinessTime::dayBounds($date);

        $load = Appointment::query()
            ->active()
            ->whereIn('staff_id', $candidates->pluck('id'))
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->selectRaw('staff_id, count(*) as total')
            ->groupBy('staff_id')
            ->pluck('total', 'staff_id');

        return $candidates
            ->sortBy(fn (Staff $staff) => [(int) ($load[$staff->id] ?? 0), $staff->sort, $staff->id])
            ->first();
    }

    /**
     * Даты, на которые открыта запись: с сегодняшней по горизонт записи.
     *
     * @return list<string> Y-m-d
     */
    public function bookableDates(?CarbonInterface $now = null): array
    {
        $today = CarbonImmutable::parse(BusinessTime::today($now));

        return collect(range(0, config('booking.horizon_days') - 1))
            ->map(fn (int $offset) => $today->addDays($offset)->toDateString())
            ->all();
    }

    public function isBookableDate(string $date, ?CarbonInterface $now = null): bool
    {
        return in_array($date, $this->bookableDates($now), true);
    }

    /**
     * Активные мастера, которые делают услугу.
     *
     * @return Collection<int, Staff>
     */
    public function staffFor(Service $service): Collection
    {
        return $service->staff()->active()->with('services')->get();
    }

    public function daySchedule(Staff $staff, string $date): DaySchedule
    {
        $timezone = BusinessTime::timezone();

        $exception = $staff->scheduleExceptions()->where('date', $date)->first();

        if ($exception !== null) {
            return $exception->type === ScheduleExceptionType::DayOff
                ? DaySchedule::dayOff($date, $timezone)
                : new DaySchedule($date, $timezone, [[$exception->starts_at, $exception->ends_at]]);
        }

        $weekday = CarbonImmutable::parse($date)->isoWeekday();

        return new DaySchedule(
            date: $date,
            timezone: $timezone,
            workingHours: $staff->workingHours()->where('weekday', $weekday)->get()
                ->map(fn ($row) => [$row->starts_at, $row->ends_at])->all(),
            breaks: $staff->breaks()->where('weekday', $weekday)->get()
                ->map(fn ($row) => [$row->starts_at, $row->ends_at])->all(),
        );
    }

    /**
     * Время мастера, занятое активными записями, с буфером после каждой.
     *
     * @return list<Interval>
     */
    private function busyIntervals(Staff $staff, string $date, ?int $ignoreAppointmentId): array
    {
        [$from, $to] = BusinessTime::dayBounds($date);

        return $staff->appointments()
            ->active()
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            // Запись вчерашнего вечера с буфером тоже может залезть на утро — берём с запасом.
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from->subDay())
            ->get()
            ->map(fn (Appointment $appointment) => new Interval($appointment->starts_at, $appointment->blockedUntil()))
            ->all();
    }
}
