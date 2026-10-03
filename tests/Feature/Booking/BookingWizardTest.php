<?php

use App\Livewire\BookingWizard;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(moscow('09:00', '2026-09-30'));
    config(['booking.slot_step' => 60]);

    $this->service = Service::factory()->create(['name' => 'Стрижка', 'duration_minutes' => 60, 'price' => 150000]);
    $this->anna = Staff::factory()->worksOn([3, 4], '10:00', '13:00')->offers($this->service)->create(['name' => 'Анна']);
    $this->ivan = Staff::factory()->worksOn([4], '15:00', '17:00')->offers($this->service, ['price' => 200000])->create(['name' => 'Иван']);
});

it('открывает страницу записи со списком услуг', function () {
    Service::factory()->inactive()->create(['name' => 'Скрытая услуга']);
    Service::factory()->create(['name' => 'Услуга без мастеров']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Онлайн-запись')
        ->assertSee('Стрижка')
        ->assertSee('1 500 ₽')
        ->assertDontSee('Скрытая услуга')
        ->assertDontSee('Услуга без мастеров');
});

it('показывает свободное время выбранного мастера в поясе бизнеса', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->assertSet('availableTimes', [
            moscow('10:00')->getTimestamp() => '10:00',
            moscow('11:00')->getTimestamp() => '11:00',
            moscow('12:00')->getTimestamp() => '12:00',
        ])
        ->assertSee('Утро');
});

it('для «любого мастера» объединяет время и показывает диапазон цен', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', 'any')
        ->call('selectDate', '2026-10-01')
        ->assertSet('availableTimes', [
            moscow('10:00')->getTimestamp() => '10:00',
            moscow('11:00')->getTimestamp() => '11:00',
            moscow('12:00')->getTimestamp() => '12:00',
            moscow('15:00')->getTimestamp() => '15:00',
            moscow('16:00')->getTimestamp() => '16:00',
        ])
        ->assertSee('от 1 500 ₽');
});

it('записывает клиента и открывает страницу записи', function () {
    $component = Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow('11:00')->getTimestamp())
        ->set('name', 'Ольга')
        ->set('phone', '8 (916) 123-45-67')
        ->set('email', 'olga@example.com')
        ->call('book');

    $appointment = Appointment::sole();

    $component->assertRedirect(route('booking.show', $appointment));
    expect($appointment->staff_id)->toBe($this->anna->id)
        ->and($appointment->starts_at->equalTo(moscow('11:00')))->toBeTrue()
        ->and(Client::sole()->phone)->toBe('+79161234567');
});

it('проверяет имя и телефон', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow('11:00')->getTimestamp())
        ->set('name', '')
        ->set('phone', '12345')
        ->set('email', 'не email')
        ->call('book')
        ->assertHasErrors(['name', 'phone', 'email']);

    expect(Appointment::count())->toBe(0);
});

it('сообщает, если время успели занять, и предлагает выбрать другое', function () {
    $component = Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow('11:00')->getTimestamp())
        ->set('name', 'Ольга')
        ->set('phone', '+79161234567');

    // Пока клиент заполнял форму, время забрал другой.
    Appointment::factory()->for($this->anna)->at(moscow('11:00'))->create();

    $component->call('book')
        ->assertHasErrors('startsAt')
        ->assertSet('startsAt', null)
        ->assertSee('Это время уже занято');

    expect(Appointment::count())->toBe(1);
});

it('не даёт выбрать время, которого нет в списке свободных', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow('11:20')->getTimestamp())
        ->assertStatus(404);
});

it('не даёт выбрать мастера, который не делает услугу', function () {
    $stranger = Staff::factory()->worksOn([4])->create();

    Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $stranger->id)
        ->assertStatus(404);
});

it('предлагает ближайшую свободную дату', function () {
    // 01.10 у Анны всё занято, следующий рабочий день — среда 07.10.
    foreach (['10:00', '11:00', '12:00'] as $time) {
        Appointment::factory()->for($this->anna)->at(moscow($time))->create();
    }

    $component = Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->assertSee('На этот день свободного времени нет');

    expect($component->instance()->nearestAvailableDate())->toBe('2026-10-07');

    $component->call('goToNearestDate')->assertSet('date', '2026-10-07');
});

it('принимает услугу и мастера из ссылки', function () {
    Livewire::withQueryParams(['service' => $this->service->id, 'master' => $this->ivan->id])
        ->test(BookingWizard::class)
        ->assertSet('serviceId', $this->service->id)
        ->assertSet('staff', (string) $this->ivan->id);
});

it('ограничивает частоту записей с одного адреса', function () {
    $book = fn (string $time, string $phone) => Livewire::test(BookingWizard::class)
        ->call('selectService', $this->service->id)
        ->call('selectStaff', 'any')
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow($time)->getTimestamp())
        ->set('name', 'Спамер')
        ->set('phone', $phone)
        ->call('book');

    foreach (['10:00', '11:00', '12:00', '15:00', '16:00'] as $i => $time) {
        $book($time, '+7916000000'.$i);
    }

    Appointment::query()->delete();
    $book('10:00', '+79160000009')->assertHasErrors('form');

    expect(Appointment::count())->toBe(0);
});

it('подставляет контакты вошедшего клиента и обновляет его email', function () {
    $client = Client::factory()->create(['name' => 'Ольга', 'phone' => '+79161234567', 'email' => 'old@example.com']);
    $this->actingAs($client, 'client');

    Livewire::test(BookingWizard::class)
        ->assertSet('name', 'Ольга')
        ->assertSet('phone', '+7 (916) 123-45-67')
        ->assertSet('email', 'old@example.com')
        ->call('selectService', $this->service->id)
        ->call('selectStaff', (string) $this->anna->id)
        ->call('selectDate', '2026-10-01')
        ->call('selectSlot', moscow('11:00')->getTimestamp())
        ->set('email', 'new@example.com')
        ->call('book');

    expect(Appointment::sole()->client_id)->toBe($client->id)
        ->and($client->fresh()->email)->toBe('new@example.com');
});
