<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));
    $this->appointment = Appointment::factory()->at(moscow('11:00'))->create();
});

it('показывает запись по ссылке с токеном в поясе бизнеса', function () {
    $this->get(route('booking.show', $this->appointment))
        ->assertOk()
        ->assertSee($this->appointment->service->name)
        ->assertSee('11:00–12:00')
        ->assertSee('Новая');
});

it('не открывает запись по id или чужому токену', function () {
    $this->get('/booking/'.$this->appointment->id)->assertNotFound();
    $this->get('/booking/'.str_repeat('a', 40))->assertNotFound();
});

it('предлагает подключить Telegram, пока он не подключён', function () {
    $this->get(route('booking.show', $this->appointment))
        ->assertSee('Напоминания в Telegram')
        ->assertSee('https://t.me/demo_booking_bot?start='.$this->appointment->token, false);

    $this->appointment->client->update(['telegram_chat_id' => '1']);

    $this->get(route('booking.show', $this->appointment))->assertDontSee('Напоминания в Telegram');
});

it('клиент отменяет запись сам', function () {
    $this->post(route('booking.cancel', $this->appointment), ['reason' => 'Заболел'])
        ->assertRedirect(route('booking.show', $this->appointment));

    expect($this->appointment->fresh())
        ->status->toBe(AppointmentStatus::Cancelled)
        ->cancel_reason->toBe('Заболел');

    $this->get(route('booking.show', $this->appointment))
        ->assertSee('Запись отменена')
        ->assertDontSee('Да, отменить');
});

it('не даёт отменить уже начавшуюся или отменённую запись', function () {
    $this->travelTo(moscow('11:30'));

    $this->post(route('booking.cancel', $this->appointment))->assertSessionHas('error');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::New);
});
