<?php

namespace App\Console\Commands;

use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Напоминания клиентам за сутки и за 2 часа. Запускается планировщиком каждые 5 минут.
 *
 * Правила:
 *  - напоминание уходит, когда до начала осталось не больше заданного срока;
 *  - каждое напоминание — не больше одного раза (отметка reminder_*_sent_at);
 *  - если клиент записался уже внутри окна (например, за 3 часа), суточное не шлём:
 *    он только что получил подтверждение;
 *  - суточное не шлём, если уже пора слать двухчасовое.
 */
#[Signature('booking:send-reminders')]
#[Description('Отправить клиентам напоминания о записях за сутки и за 2 часа')]
class SendAppointmentReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        // Сначала двухчасовые: тогда суточное для той же записи уже не нужно.
        foreach ([ReminderKind::Hours, ReminderKind::Day] as $kind) {
            $sent += $this->send($kind);
        }

        $this->info("Отправлено напоминаний: {$sent}.");

        return self::SUCCESS;
    }

    private function send(ReminderKind $kind): int
    {
        $now = now();
        $column = $kind->sentAtColumn();
        $sent = 0;

        $appointments = Appointment::query()
            ->active()
            ->whereNull($column)
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $now->copy()->addMinutes($kind->minutesBefore()))
            ->with(['client', 'service', 'staff'])
            ->get();

        foreach ($appointments as $appointment) {
            // Атомарно «забираем» напоминание: если параллельный запуск успел раньше — пропускаем.
            $claimed = Appointment::query()->whereKey($appointment->id)->whereNull($column)->update([$column => $now]);

            if ($claimed === 0 || ! $this->shouldSend($appointment, $kind)) {
                continue;
            }

            $appointment->client->notify(new AppointmentReminder($appointment, $kind));
            $sent++;
        }

        return $sent;
    }

    private function shouldSend(Appointment $appointment, ReminderKind $kind): bool
    {
        $windowStart = $appointment->starts_at->subMinutes($kind->minutesBefore());

        if ($appointment->created_at->greaterThan($windowStart)) {
            return false;
        }

        if ($kind === ReminderKind::Day && $appointment->reminder_hours_sent_at !== null) {
            return false;
        }

        return true;
    }
}
