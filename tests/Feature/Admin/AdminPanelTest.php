<?php

use App\Enums\AppointmentStatus;
use App\Filament\Calendar\AppointmentCalendarWidget;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));
    config(['booking.slot_step' => 60]);

    $this->actingAs(User::factory()->create());

    $this->service = Service::factory()->create(['name' => 'Стрижка', 'duration_minutes' => 60]);
    $this->staff = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service)->create(['name' => 'Анна']);
});

it('открывает все разделы админки', function (string $url) {
    $this->get($url)->assertOk();
})->with([
    'дашборд' => fn () => '/admin',
    'календарь' => fn () => Calendar::getUrl(),
    'записи' => fn () => AppointmentResource::getUrl(),
    'новая запись' => fn () => AppointmentResource::getUrl('create'),
    'карточка записи' => fn () => AppointmentResource::getUrl('view', ['record' => Appointment::factory()->at(moscow('11:00'))->create()]),
    'клиенты' => fn () => ClientResource::getUrl(),
    'карточка клиента' => fn () => ClientResource::getUrl('view', ['record' => Client::factory()->create()]),
    'услуги' => fn () => ServiceResource::getUrl(),
    'мастера' => fn () => StaffResource::getUrl(),
    'карточка мастера' => fn () => StaffResource::getUrl('edit', ['record' => Staff::first()]),
]);

it('не пускает в админку гостя', function () {
    auth()->logout();

    $this->get('/admin')->assertRedirect('/admin/login');
});

it('в демо-режиме подставляет доступ на странице входа', function () {
    auth()->logout();
    config(['booking.demo.enabled' => true]);

    $this->get('/admin/login')->assertOk()->assertSee('admin@example.com');
});

it('хранит цену услуги в копейках, а показывает в рублях', function () {
    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Укладка',
            'duration_minutes' => 45,
            'buffer_minutes' => 0,
            'price' => 1800.50,
            'sort' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Service::where('name', 'Укладка')->sole()->price)->toBe(180050);
});

it('сохраняет график мастера в местном времени без перевода в UTC', function () {
    $undoRepeaterFake = Repeater::fake();

    // Панель работает в поясе Москвы (UTC+3): без защиты 09:30 сохранилось бы как 06:30.
    // Не-нативный пикер держит в состоянии полный datetime — так его присылает браузер.
    Livewire::test(EditStaff::class, ['record' => $this->staff->getRouteKey()])
        ->fillForm([
            'workingHours' => [['weekday' => 1, 'starts_at' => '2026-09-30 09:30:00', 'ends_at' => '2026-09-30 18:00:00']],
            'breaks' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    expect(WorkingHour::where('staff_id', $this->staff->id)->sole())
        ->weekday->toBe(1)
        ->starts_at->toStartWith('09:30')
        ->ends_at->toStartWith('18:00');
});

it('не даёт удалить мастера, у которого есть записи', function () {
    Livewire::test(EditStaff::class, ['record' => $this->staff->getRouteKey()])
        ->assertActionVisible('delete');

    Appointment::factory()->for($this->staff)->at(moscow('11:00'))->create();

    Livewire::test(EditStaff::class, ['record' => $this->staff->getRouteKey()])
        ->assertActionHidden('delete');
});

it('создаёт запись по телефону только на свободное время', function () {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'service_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'date' => '2026-10-01',
            'slot' => moscow('12:00')->getTimestamp(),
            'phone' => '8 916 111-22-33',
            'name' => 'Пётр',
            'confirm' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Appointment::sole())
        ->status->toBe(AppointmentStatus::Confirmed)
        ->starts_at->equalTo(moscow('12:00'))->toBeTrue()
        ->and(Client::sole()->phone)->toBe('+79161112233');
});

it('предлагает в форме только свободное время', function () {
    Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('11:00'))->create();

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'service_id' => $this->service->id,
            'staff_id' => $this->staff->id,
            'date' => '2026-10-01',
        ])
        ->assertFormFieldExists('slot', fn ($field) => array_values($field->getOptions()) === ['10:00', '12:00', '13:00', '14:00']);
});

it('подтверждает и отменяет запись из таблицы', function () {
    $appointment = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('11:00'))->create();

    Livewire::test(ListAppointments::class)
        ->callAction(TestAction::make('confirm')->table($appointment));

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

    Livewire::test(ListAppointments::class)
        ->callAction(TestAction::make('cancel')->table($appointment), ['reason' => 'Мастер заболел']);

    expect($appointment->fresh())
        ->status->toBe(AppointmentStatus::Cancelled)
        ->cancel_reason->toBe('Мастер заболел');
});

it('переносит запись из карточки', function () {
    $appointment = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('11:00'))->create();

    Livewire::test(ViewAppointment::class, ['record' => $appointment->getRouteKey()])
        ->callAction('reschedule', [
            'staff_id' => $this->staff->id,
            'date' => '2026-10-01',
            'slot' => moscow('14:00')->getTimestamp(),
        ])
        ->assertHasNoFormErrors();

    expect($appointment->fresh()->starts_at->equalTo(moscow('14:00')))->toBeTrue();
});

it('показывает в календаре записи мастеров, кроме отменённых', function () {
    $active = Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('11:00'))->create();
    Appointment::factory()->for($this->staff)->for($this->service)->at(moscow('13:00'))->status(AppointmentStatus::Cancelled)->create();

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->getEventsJs(['startStr' => '2026-10-01T00:00:00+03:00', 'endStr' => '2026-10-02T00:00:00+03:00', 'tzOffset' => 180]);

    expect($events)->toHaveCount(1)
        ->and($events[0]['title'])->toContain($active->client->name)
        ->and($events[0]['resourceIds'])->toBe([$this->staff->id]);
});
