<?php

use App\Enums\AppointmentStatus;
use App\Livewire\Cabinet\Appointments;
use App\Livewire\Cabinet\History;
use App\Livewire\Cabinet\Profile;
use App\Livewire\Cabinet\Reschedule;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Admin\AppointmentCancelledByClient;
use App\Notifications\Admin\AppointmentRescheduledByClient;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));
    config(['booking.slot_step' => 60]);

    $this->service = Service::factory()->create(['name' => 'Стрижка', 'duration_minutes' => 60]);
    $this->staff = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service)->create(['name' => 'Анна']);
    $this->client = Client::factory()->create(['name' => 'Ольга']);

    $this->upcoming = Appointment::factory()->for($this->client)->for($this->staff)->for($this->service)
        ->status(AppointmentStatus::Confirmed)->at(moscow('11:00'))->create();

    $this->actingAs($this->client, 'client');
});

it('показывает предстоящие записи клиента и не показывает чужие и прошедшие', function () {
    Appointment::factory()->for($this->client)->for($this->staff)->for($this->service)
        ->status(AppointmentStatus::Completed)->at(moscow('11:00', '2026-09-20'))->create();
    Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('13:00'))->create();
    Appointment::factory()->for($this->client)->for($this->staff)->for($this->service)
        ->status(AppointmentStatus::Cancelled)->at(moscow('12:00'))->create();

    Livewire::test(Appointments::class)
        ->assertSee('Здравствуйте, Ольга!')
        ->assertSee('Визитов в студию: 1')
        ->assertSee('Четверг, 1 октября, 11:00–12:00')
        ->assertSee('Подтверждена')
        ->assertSee('Перенести')
        ->assertSet('upcoming', fn ($upcoming) => $upcoming->pluck('id')->all() === [$this->upcoming->id]);
});

it('клиент отменяет свою запись из кабинета', function () {
    $admin = User::factory()->create();

    Livewire::test(Appointments::class)
        ->set('cancelReason', 'Заболела')
        ->call('cancel', $this->upcoming->id)
        ->assertSee('Запись отменена')
        ->assertSee('Предстоящих записей нет');

    expect($this->upcoming->fresh())
        ->status->toBe(AppointmentStatus::Cancelled)
        ->cancel_reason->toBe('Заболела');

    Notification::assertSentTo($admin, AppointmentCancelledByClient::class);
});

it('не даёт отменить чужую запись', function () {
    $foreign = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('13:00'))->create();

    Livewire::test(Appointments::class)->call('cancel', $foreign->id)->assertNotFound();

    expect($foreign->fresh()->status)->toBe(AppointmentStatus::New);
});

it('не даёт отменить запись меньше чем за час до начала', function () {
    $this->travelTo(moscow('10:01'));

    Livewire::test(Appointments::class)
        ->assertSee('До начала меньше 60 мин')
        ->assertDontSee('Перенести')
        ->call('cancel', $this->upcoming->id)
        ->assertSee('уже нельзя отменить');

    expect($this->upcoming->fresh()->status)->toBe(AppointmentStatus::Confirmed);
});

it('правило отмены: ровно за час ещё можно, позже — нет', function () {
    expect($this->upcoming->canBeCancelledByClient(moscow('10:00')))->toBeTrue()
        ->and($this->upcoming->canBeCancelledByClient(moscow('10:00:01')))->toBeFalse();
});

it('переносит запись на свободное время того же мастера', function () {
    $admin = User::factory()->create();
    Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('13:00'))->create();

    Livewire::test(Reschedule::class, ['appointment' => $this->upcoming])
        ->assertSet('date', '2026-10-01')
        // 11:00 — текущее время записи, 13:00 занято другим клиентом.
        ->assertSet('availableTimes', [
            moscow('10:00')->getTimestamp() => '10:00',
            moscow('12:00')->getTimestamp() => '12:00',
            moscow('14:00')->getTimestamp() => '14:00',
        ])
        ->call('selectSlot', moscow('14:00')->getTimestamp())
        ->call('reschedule')
        ->assertRedirect(route('cabinet'));

    expect($this->upcoming->fresh())
        ->starts_at->toEqual(moscow('14:00'))
        // Подтверждённое время сменилось — администратор подтверждает заново.
        ->status->toBe(AppointmentStatus::New)
        ->confirmed_at->toBeNull();

    Notification::assertSentTo($this->client, AppointmentRescheduled::class);
    Notification::assertSentTo($admin, AppointmentRescheduledByClient::class);
});

it('сообщает, если время успели занять', function () {
    $component = Livewire::test(Reschedule::class, ['appointment' => $this->upcoming])
        ->call('selectSlot', moscow('14:00')->getTimestamp());

    Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('14:00'))->create();

    $component->call('reschedule')->assertHasErrors('startsAt')->assertSet('startsAt', null);

    expect($this->upcoming->fresh()->starts_at)->toEqual(moscow('11:00'));
});

it('не открывает перенос чужой или уже не переносимой записи', function () {
    $foreign = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('13:00'))->create();

    $this->get(route('cabinet.reschedule', $foreign))->assertNotFound();

    $this->travelTo(moscow('10:30'));

    $this->get(route('cabinet.reschedule', $this->upcoming))->assertRedirect(route('cabinet'));
});

it('не даёт выбрать время, которого нет в списке', function () {
    Livewire::test(Reschedule::class, ['appointment' => $this->upcoming])
        ->call('selectSlot', moscow('11:00')->getTimestamp())
        ->assertNotFound();
});

it('показывает историю визитов и итоги', function () {
    $ivan = Staff::factory()->offers($this->service)->create(['name' => 'Иван']);

    foreach (['2026-09-10', '2026-09-17'] as $date) {
        Appointment::factory()->for($this->client)->for($this->staff)->for($this->service)
            ->status(AppointmentStatus::Completed)->at(moscow('11:00', $date))->create(['price' => 200000]);
    }
    Appointment::factory()->for($this->client)->for($ivan)->for($this->service)
        ->status(AppointmentStatus::Completed)->at(moscow('11:00', '2026-09-20'))->create(['price' => 150000]);
    Appointment::factory()->for($this->client)->for($this->staff)->for($this->service)
        ->status(AppointmentStatus::Cancelled)->at(moscow('12:00', '2026-10-02'))->create(['cancel_reason' => 'Уехала']);
    // Чужой визит в историю и итоги не попадает.
    Appointment::factory()->for($this->staff)->for($this->service)
        ->status(AppointmentStatus::Completed)->at(moscow('11:00', '2026-09-21'))->create();

    Livewire::test(History::class)
        ->assertSet('stats', ['visits' => 3, 'spent' => 550000, 'favoriteStaff' => 'Анна'])
        ->assertSee('5 500 ₽')
        ->assertSee('Причина отмены: Уехала')
        ->assertSee('Записаться снова')
        ->assertSee(e(route('home', ['service' => $this->service->id, 'master' => $ivan->id])), false)
        ->assertSet('appointments', fn ($page) => $page->total() === 4);
});

it('сохраняет имя и email в профиле', function () {
    Livewire::test(Profile::class)
        ->assertSet('name', 'Ольга')
        ->set('name', 'Ольга Петрова')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors('email')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Сохранено');

    expect($this->client->fresh())
        ->name->toBe('Ольга Петрова')
        ->email->toBe('new@example.com');
});

it('подключает и отключает Telegram в профиле', function () {
    Livewire::test(Profile::class)
        ->assertSee('Подключить Telegram')
        ->assertSee('https://t.me/demo_booking_bot?start='.$this->upcoming->token, false);

    $this->client->update(['telegram_chat_id' => '555']);

    Livewire::test(Profile::class)
        ->assertSee('Подключён')
        ->call('disconnectTelegram');

    expect($this->client->fresh()->telegram_chat_id)->toBeNull();
});

it('открывает все страницы кабинета', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('Личный кабинет');
})->with(['cabinet', 'cabinet.history', 'cabinet.profile']);
