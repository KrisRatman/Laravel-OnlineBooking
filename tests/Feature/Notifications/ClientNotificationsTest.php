<?php

use App\Enums\AppointmentStatus;
use App\Enums\ReminderKind;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentReminder;
use App\Notifications\Channels\TelegramChannel;

beforeEach(function () {
    $service = Service::factory()->create(['name' => 'Стрижка']);
    $this->appointment = Appointment::factory()->for($service)->at(moscow('11:00'))->create(['price' => 150000]);
});

it('выбирает каналы по контактам клиента', function (array $contacts, array $channels) {
    $client = Client::factory()->create($contacts);

    expect((new AppointmentBooked($this->appointment))->via($client))->toBe($channels);
})->with([
    'только email' => [['email' => 'a@example.com', 'telegram_chat_id' => null], ['mail']],
    'только Telegram' => [['email' => null, 'telegram_chat_id' => '1'], [TelegramChannel::class]],
    'оба' => [['email' => 'a@example.com', 'telegram_chat_id' => '1'], ['mail', TelegramChannel::class]],
    'ничего' => [['email' => null, 'telegram_chat_id' => null], []],
]);

it('пишет письмо на русском с временем в поясе бизнеса и ссылкой на запись', function () {
    $mail = (new AppointmentReminder($this->appointment, ReminderKind::Day))->toMail($this->appointment->client);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Напоминание: завтра у вас запись')
        ->and($html)->toContain('Стрижка')
        ->toContain('1 октября (чт), 11:00')
        ->toContain('1 500 ₽')
        ->toContain(route('booking.show', $this->appointment));
});

it('в уведомлении об отмене не даёт ссылку на отмену', function () {
    $this->appointment->update(['status' => AppointmentStatus::Cancelled, 'cancel_reason' => 'Мастер заболел']);

    $text = (new AppointmentCancelled($this->appointment))->toTelegram($this->appointment->client);

    expect($text)->toContain('Мастер заболел')
        ->not->toContain(route('booking.show', $this->appointment));
});

it('экранирует данные клиента в Telegram-разметке', function () {
    $this->appointment->service->update(['name' => '<b>Стрижка</b> & укладка']);

    $text = (new AppointmentBooked($this->appointment->fresh()))->toTelegram($this->appointment->client);

    expect($text)->toContain('&lt;b&gt;Стрижка&lt;/b&gt; &amp; укладка');
});
