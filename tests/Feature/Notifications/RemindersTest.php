<?php

use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

/** Запись на 01.10 12:00 по Москве, созданная заранее. */
function upcoming(array $attributes = []): Appointment
{
    return Appointment::factory()->at(moscow('12:00'))->create([
        'created_at' => moscow('09:00', '2026-09-25'),
        ...$attributes,
    ]);
}

function remindersSent(Appointment $appointment, ReminderKind $kind): int
{
    return Notification::sent($appointment->client, AppointmentReminder::class)
        ->filter(fn (AppointmentReminder $n) => $n->appointment->is($appointment) && $n->kind === $kind)
        ->count();
}

it('не шлёт напоминание раньше чем за сутки', function () {
    $appointment = upcoming();
    $this->travelTo(moscow('11:55', '2026-09-30'));

    $this->artisan('booking:send-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect($appointment->fresh()->reminder_day_sent_at)->toBeNull();
});

it('шлёт напоминание за сутки один раз', function () {
    $appointment = upcoming();
    $this->travelTo(moscow('12:00', '2026-09-30'));

    $this->artisan('booking:send-reminders')->expectsOutputToContain('Отправлено напоминаний: 1');
    $this->artisan('booking:send-reminders')->expectsOutputToContain('Отправлено напоминаний: 0');

    expect(remindersSent($appointment, ReminderKind::Day))->toBe(1)
        ->and($appointment->fresh()->reminder_day_sent_at)->not->toBeNull();
});

it('шлёт напоминание за 2 часа после суточного', function () {
    $appointment = upcoming();

    $this->travelTo(moscow('12:00', '2026-09-30'));
    $this->artisan('booking:send-reminders');
    $this->travelTo(moscow('10:00'));
    $this->artisan('booking:send-reminders');

    expect(remindersSent($appointment, ReminderKind::Day))->toBe(1)
        ->and(remindersSent($appointment, ReminderKind::Hours))->toBe(1);
});

it('если планировщик пропустил суточное окно, шлёт только двухчасовое', function () {
    $appointment = upcoming();
    $this->travelTo(moscow('10:30'));

    $this->artisan('booking:send-reminders');

    expect(remindersSent($appointment, ReminderKind::Hours))->toBe(1)
        ->and(remindersSent($appointment, ReminderKind::Day))->toBe(0)
        ->and($appointment->fresh()->reminder_day_sent_at)->not->toBeNull();
});

it('не шлёт суточное, если клиент записался меньше чем за сутки', function () {
    $appointment = upcoming(['created_at' => moscow('20:00', '2026-09-30')]);
    $this->travelTo(moscow('20:05', '2026-09-30'));

    $this->artisan('booking:send-reminders');

    expect(remindersSent($appointment, ReminderKind::Day))->toBe(0);

    // А за 2 часа — напомним.
    $this->travelTo(moscow('10:00'));
    $this->artisan('booking:send-reminders');

    expect(remindersSent($appointment, ReminderKind::Hours))->toBe(1);
});

it('не напоминает об отменённых, выполненных и прошедших записях', function () {
    upcoming(['status' => AppointmentStatus::Cancelled]);
    upcoming(['status' => AppointmentStatus::Completed]);
    Appointment::factory()->at(moscow('08:00'))->create(['created_at' => moscow('09:00', '2026-09-25')]);
    $this->travelTo(moscow('10:00'));

    $this->artisan('booking:send-reminders');

    Notification::assertNothingSent();
});

it('напоминает о подтверждённых записях тоже', function () {
    $appointment = upcoming(['status' => AppointmentStatus::Confirmed]);
    $this->travelTo(moscow('10:00'));

    $this->artisan('booking:send-reminders');

    expect(remindersSent($appointment, ReminderKind::Hours))->toBe(1);
});

it('запускается планировщиком каждые 5 минут', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'booking:send-reminders'));

    expect($event?->expression)->toBe('*/5 * * * *');
});
