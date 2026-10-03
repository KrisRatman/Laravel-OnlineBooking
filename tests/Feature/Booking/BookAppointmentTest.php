<?php

use App\Actions\BookAppointment;
use App\Actions\BookingRequest;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Admin\NewAppointmentForAdmin;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentConfirmed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

function bookingRequest(Service $service, ?Staff $staff, CarbonImmutable $start, array $overrides = []): BookingRequest
{
    return new BookingRequest(...[
        'serviceId' => $service->id,
        'staffId' => $staff?->id,
        'startsAt' => $start,
        'name' => 'Ольга',
        'phone' => '+79161234567',
        'email' => 'olga@example.com',
        ...$overrides,
    ]);
}

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));

    $this->service = Service::factory()->create(['duration_minutes' => 60, 'buffer_minutes' => 15, 'price' => 200000]);
    $this->staff = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service, ['price' => 250000])->create();
    $this->admin = User::factory()->create();
});

it('создаёт запись со снимком условий мастера', function () {
    $appointment = app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00')));

    expect($appointment->status)->toBe(AppointmentStatus::New)
        ->and($appointment->starts_at->toDateTimeString())->toBe('2026-10-01 08:00:00')
        ->and($appointment->ends_at->toDateTimeString())->toBe('2026-10-01 09:00:00')
        ->and($appointment->price)->toBe(250000)
        ->and($appointment->buffer_minutes)->toBe(15)
        ->and($appointment->token)->toHaveLength(40);
});

it('узнаёт клиента по телефону и обновляет имя', function () {
    $client = Client::factory()->create(['phone' => '+79161234567', 'name' => 'Оля', 'email' => 'old@example.com']);

    $appointment = app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00'), ['email' => null]));

    expect($appointment->client_id)->toBe($client->id)
        ->and($client->fresh()->name)->toBe('Ольга')
        ->and($client->fresh()->email)->toBe('old@example.com')
        ->and(Client::count())->toBe(1);
});

it('не даёт занять уже занятое время', function () {
    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00')));

    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00'), ['phone' => '+79160000000']));
})->throws(SlotUnavailableException::class);

it('не даёт записаться вне сетки, в прошлое и в нерабочее время', function (string $time, string $date) {
    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow($time, $date)));
})->with([
    'вне сетки' => ['11:10', '2026-10-01'],
    'после закрытия' => ['14:30', '2026-10-01'],
    'вчера' => ['11:00', '2026-09-29'],
    'выходной' => ['11:00', '2026-10-03'],
])->throws(SlotUnavailableException::class);

it('с «любым мастером» выбирает свободного и наименее загруженного', function () {
    $second = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service)->create();
    Appointment::factory()->for($this->staff)->at(moscow('13:00'))->create();

    $appointment = app(BookAppointment::class)->handle(bookingRequest($this->service, null, moscow('11:00')));

    expect($appointment->staff_id)->toBe($second->id)
        ->and($appointment->price)->toBe(200000);
});

it('с «любым мастером» отказывает, если свободных нет', function () {
    Appointment::factory()->for($this->staff)->at(moscow('11:00'))->create();

    app(BookAppointment::class)->handle(bookingRequest($this->service, null, moscow('11:00')));
})->throws(SlotUnavailableException::class);

it('уведомляет клиента и администраторов', function () {
    $appointment = app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00')));

    Notification::assertSentTo($appointment->client, AppointmentBooked::class);
    Notification::assertSentTo($this->admin, NewAppointmentForAdmin::class);
});

it('запись из админки можно сразу подтвердить без оповещения администраторов', function () {
    $appointment = app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00'), ['confirmed' => true, 'fromAdmin' => true]));

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->confirmed_at)->not->toBeNull();
    Notification::assertSentTo($appointment->client, AppointmentConfirmed::class);
    Notification::assertNotSentTo($appointment->client, AppointmentBooked::class);
    Notification::assertNotSentTo($this->admin, NewAppointmentForAdmin::class);
});

it('анонимная запись не заменяет сохранённый email клиента, но заполняет пустой', function () {
    $client = Client::factory()->create(['phone' => '+79161234567', 'email' => 'owner@example.com']);

    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00'), ['email' => 'stranger@example.com']));

    expect($client->fresh()->email)->toBe('owner@example.com');

    $client->update(['email' => null]);
    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('13:00'), ['email' => 'new@example.com']));

    expect($client->fresh()->email)->toBe('new@example.com');
});

it('вошедший клиент и администратор могут обновить email', function (array $flags) {
    $client = Client::factory()->create(['phone' => '+79161234567', 'email' => 'old@example.com']);

    app(BookAppointment::class)->handle(bookingRequest($this->service, $this->staff, moscow('11:00'), ['email' => 'new@example.com', ...$flags]));

    expect($client->fresh()->email)->toBe('new@example.com');
})->with([
    'клиент в кабинете' => [['authenticated' => true]],
    'администратор' => [['fromAdmin' => true]],
]);
