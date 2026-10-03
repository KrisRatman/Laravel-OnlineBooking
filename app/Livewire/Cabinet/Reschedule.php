<?php

namespace App\Livewire\Cabinet;

use App\Actions\AppointmentWorkflow;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Services\Slots\SlotService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * Перенос записи клиентом: та же услуга и тот же мастер, другое время.
 */
#[Layout('layouts.app')]
#[Title('Перенос записи')]
class Reschedule extends CabinetPage
{
    #[Locked]
    public int $appointmentId;

    public ?string $date = null;

    /** Новое начало: unix timestamp (UTC). */
    public ?int $startsAt = null;

    public function mount(Appointment $appointment): void
    {
        abort_unless($appointment->client_id === $this->client()->id, 404);

        if (! $appointment->canBeRescheduledByClient()) {
            session()->flash('error', 'Эту запись уже нельзя перенести онлайн — позвоните нам.');
            $this->redirectRoute('cabinet');

            return;
        }

        $this->appointmentId = $appointment->id;
        $this->date = in_array($date = $appointment->localStartsAt()->toDateString(), $this->dates, true)
            ? $date
            : ($this->dates[0] ?? null);
    }

    public function selectDate(string $date): void
    {
        abort_unless(in_array($date, $this->dates, true), 404);

        $this->date = $date;
        $this->startsAt = null;
        unset($this->availableTimes);
    }

    public function selectSlot(int $timestamp): void
    {
        abort_unless(array_key_exists($timestamp, $this->availableTimes), 404);

        $this->startsAt = $timestamp;
    }

    public function reschedule(AppointmentWorkflow $workflow): void
    {
        $this->validate(['startsAt' => ['required', 'integer']], ['startsAt.required' => 'Выберите новое время.']);

        $appointment = $this->appointment;

        if (! $appointment->canBeRescheduledByClient()) {
            session()->flash('error', 'Эту запись уже нельзя перенести онлайн — позвоните нам.');

            $this->redirectRoute('cabinet');

            return;
        }

        try {
            $workflow->reschedule($appointment, $appointment->staff_id, CarbonImmutable::createFromTimestampUTC($this->startsAt), byClient: true);
        } catch (SlotUnavailableException $e) {
            $this->startsAt = null;
            unset($this->availableTimes);
            $this->addError('startsAt', $e->getMessage());

            return;
        }

        session()->flash('status', 'Запись перенесена. Администратор подтвердит новое время.');

        $this->redirectRoute('cabinet');
    }

    #[Computed]
    public function appointment(): Appointment
    {
        return $this->findAppointment($this->appointmentId)->load(['service', 'staff']);
    }

    /** @return list<string> */
    #[Computed]
    public function dates(): array
    {
        return app(SlotService::class)->bookableDates();
    }

    /**
     * Свободное время мастера на выбранную дату, кроме текущего времени записи.
     *
     * @return array<int, string> timestamp → «HH:MM»
     */
    #[Computed]
    public function availableTimes(): array
    {
        if ($this->date === null) {
            return [];
        }

        $appointment = $this->appointment;

        return collect(app(SlotService::class)->availableSlots(
            $appointment->service,
            $appointment->staff,
            $this->date,
            ignoreAppointmentId: $appointment->id,
        ))
            ->reject(fn (CarbonImmutable $start) => $start->equalTo($appointment->starts_at))
            ->mapWithKeys(fn (CarbonImmutable $start) => [$start->getTimestamp() => BusinessTime::local($start)->format('H:i')])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.cabinet.reschedule', [
            'selectedStart' => $this->startsAt ? BusinessTime::local(CarbonImmutable::createFromTimestampUTC($this->startsAt)) : null,
        ]);
    }
}
