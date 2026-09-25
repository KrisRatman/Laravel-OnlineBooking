<?php

use App\Actions\AppointmentWorkflow;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Admin\AppointmentCancelledByClient;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));

    $this->service = Service::factory()->create(['duration_minutes' => 60]);
    $this->staff = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service)->create();
    $this->appointment = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('11:00'))->create();
    $this->workflow = app(AppointmentWorkflow::class);
});

it('подтверждает новую запись и уведомляет клиента', function () {
    $this->workflow->confirm($this->appointment);

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed)
        ->and($this->appointment->fresh()->confirmed_at)->not->toBeNull();
    Notification::assertSentTo($this->appointment->client, AppointmentConfirmed::class);
});

it('отменяет запись с причиной и освобождает время', function () {
    $this->workflow->cancel($this->appointment, 'Мастер заболел');

    expect($this->appointment->fresh())
        ->status->toBe(AppointmentStatus::Cancelled)
        ->cancel_reason->toBe('Мастер заболел');
    Notification::assertSentTo($this->appointment->client, AppointmentCancelled::class, fn ($n) => $n->byClient === false);
});

it('при отмене клиентом оповещает администраторов', function () {
    $admin = User::factory()->create();

    $this->workflow->cancel($this->appointment, byClient: true);

    expect($this->appointment->fresh()->cancel_reason)->toBe('Отменена клиентом');
    Notification::assertSentTo($admin, AppointmentCancelledByClient::class);
});

it('не переводит запись в недопустимый статус', function (AppointmentStatus $from, string $method) {
    $this->appointment->update(['status' => $from]);

    $this->workflow->{$method}($this->appointment);
})->with([
    'подтвердить отменённую' => [AppointmentStatus::Cancelled, 'confirm'],
    'подтвердить подтверждённую' => [AppointmentStatus::Confirmed, 'confirm'],
    'отменить выполненную' => [AppointmentStatus::Completed, 'cancel'],
    'выполнить отменённую' => [AppointmentStatus::Cancelled, 'complete'],
])->throws(InvalidStatusTransitionException::class);

it('переносит запись на свободное время и сбрасывает напоминания', function () {
    $this->appointment->update(['reminder_day_sent_at' => now()]);

    $this->workflow->reschedule($this->appointment, $this->staff->id, moscow('13:00'));

    expect($this->appointment->fresh())
        ->starts_at->toDateTimeString()->toBe('2026-10-01 10:00:00')
        ->reminder_day_sent_at->toBeNull();
    Notification::assertSentTo($this->appointment->client, AppointmentRescheduled::class);
});

it('переносит на соседний час, пересекающийся со своим же старым временем', function () {
    config(['booking.slot_step' => 30]);

    $this->workflow->reschedule($this->appointment, $this->staff->id, moscow('11:30'));

    expect($this->appointment->fresh()->starts_at->toDateTimeString())->toBe('2026-10-01 08:30:00');
});

it('переносит к другому мастеру с его условиями', function () {
    $other = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service, ['price' => 999900, 'duration_minutes' => 90])->create();

    $this->workflow->reschedule($this->appointment, $other->id, moscow('11:00'));

    expect($this->appointment->fresh())
        ->staff_id->toBe($other->id)
        ->price->toBe(999900)
        ->and($this->appointment->fresh()->durationMinutes())->toBe(90);
});

it('не переносит на занятое время', function () {
    Appointment::factory()->for($this->staff)->at(moscow('13:00'))->create();

    $this->workflow->reschedule($this->appointment, $this->staff->id, moscow('13:00'));
})->throws(SlotUnavailableException::class);
