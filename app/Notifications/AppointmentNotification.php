<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Client;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Письмо и сообщение в Telegram клиенту о его записи. Каналы — те, что клиент оставил.
 */
#[DeleteWhenMissingModels]
abstract class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
    ) {
        // Не отправлять, пока транзакция с записью не зафиксирована.
        $this->afterCommit();
    }

    abstract protected function subject(): string;

    abstract protected function intro(): string;

    /** Показывать ли кнопку «Управлять записью». */
    protected function withManageLink(): bool
    {
        return $this->appointment->status->isActive();
    }

    /** @return list<string> */
    public function via(Client $notifiable): array
    {
        return array_values(array_filter([
            filled($notifiable->email) ? 'mail' : null,
            filled($notifiable->telegram_chat_id) ? TelegramChannel::class : null,
        ]));
    }

    public function toMail(Client $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Здравствуйте, {$notifiable->name}!")
            ->line($this->intro());

        foreach ($this->details() as $label => $value) {
            $message->line("**{$label}:** {$value}");
        }

        if ($this->withManageLink()) {
            $message->action('Посмотреть или отменить запись', $this->manageUrl());
        }

        return $message->salutation('До встречи! '.config('app.name'));
    }

    public function toTelegram(Client $notifiable): string
    {
        $lines = ['<b>'.e($this->subject()).'</b>', '', e($this->intro()), ''];

        foreach ($this->details() as $label => $value) {
            $lines[] = e($label).': <b>'.e($value).'</b>';
        }

        if ($this->withManageLink()) {
            $lines[] = '';
            $lines[] = '<a href="'.e($this->manageUrl()).'">Посмотреть или отменить запись</a>';
        }

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    protected function details(): array
    {
        $appointment = $this->appointment;

        return [
            'Услуга' => $appointment->service->name,
            'Мастер' => $appointment->staff->name,
            'Когда' => $this->when(),
            'Стоимость' => money_rub($appointment->price),
        ];
    }

    protected function when(): string
    {
        return $this->appointment->localStartsAt()->locale('ru')->isoFormat('D MMMM (dd), HH:mm');
    }

    protected function manageUrl(): string
    {
        return route('booking.show', $this->appointment);
    }
}
