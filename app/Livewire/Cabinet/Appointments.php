<?php

namespace App\Livewire\Cabinet;

use App\Actions\AppointmentWorkflow;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Главная кабинета: предстоящие записи с отменой и переносом.
 */
#[Layout('layouts.app')]
#[Title('Мои записи')]
class Appointments extends CabinetPage
{
    public string $cancelReason = '';

    public function cancel(int $appointmentId, AppointmentWorkflow $workflow): void
    {
        $appointment = $this->findAppointment($appointmentId);

        if (! $appointment->canBeCancelledByClient()) {
            session()->now('error', 'Эту запись уже нельзя отменить онлайн — позвоните нам.');

            return;
        }

        $workflow->cancel($appointment, filled($this->cancelReason) ? trim(mb_substr($this->cancelReason, 0, 255)) : null, byClient: true);

        $this->reset('cancelReason');
        unset($this->upcoming);
        session()->now('status', 'Запись отменена.');
    }

    /** @return Collection<int, Appointment> */
    #[Computed]
    public function upcoming(): Collection
    {
        return $this->client()->appointments()
            ->active()
            ->where('ends_at', '>', now())
            ->with(['service', 'staff'])
            ->orderBy('starts_at')
            ->get();
    }

    #[Computed]
    public function visitsCount(): int
    {
        return $this->client()->appointments()->where('status', AppointmentStatus::Completed)->count();
    }

    public function render(): View
    {
        return view('livewire.cabinet.appointments', [
            'client' => $this->client(),
            'deadlineMinutes' => config('booking.client_change_deadline_minutes'),
            'businessPhone' => config('booking.business.phone'),
        ]);
    }
}
