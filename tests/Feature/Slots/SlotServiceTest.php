<?php

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Slots\SlotService;
use Carbon\CarbonImmutable;

// 01.10.2026 — четверг. «Сейчас» — накануне утром по Москве.
const THURSDAY = '2026-10-01';

/** @return list<string> */
function localSlots(Service $service, Staff $staff, string $date = THURSDAY): array
{
    return array_map(
        fn (CarbonImmutable $slot) => $slot->setTimezone('Europe/Moscow')->format('H:i'),
        app(SlotService::class)->availableSlots($service, $staff, $date),
    );
}

beforeEach(function () {
    $this->travelTo(moscow('09:00', '2026-09-30'));
    config(['booking.slot_step' => 60]);

    $this->service = Service::factory()->create(['duration_minutes' => 60]);
    $this->staff = Staff::factory()->worksOn([1, 2, 3, 4, 5], '10:00', '15:00')->offers($this->service)->create();
});

it('строит слоты по недельному шаблону в поясе бизнеса', function () {
    expect(localSlots($this->service, $this->staff))->toBe(['10:00', '11:00', '12:00', '13:00', '14:00']);

    $first = app(SlotService::class)->availableSlots($this->service, $this->staff, THURSDAY)[0];
    expect($first->toDateTimeString())->toBe('2026-10-01 07:00:00');
});

it('не даёт слотов в день недели без рабочих часов', function () {
    // 03.10.2026 — суббота.
    expect(localSlots($this->service, $this->staff, '2026-10-03'))->toBe([]);
});

it('вычитает перерыв из шаблона', function () {
    $staff = Staff::factory()->worksOn([4], '10:00', '15:00')->withBreak('12:00', '13:00', [4])->offers($this->service)->create();

    expect(localSlots($this->service, $staff))->toBe(['10:00', '11:00', '13:00', '14:00']);
});

describe('исключения из расписания', function () {
    it('выходной по исключению закрывает день', function () {
        ScheduleException::create(['staff_id' => $this->staff->id, 'date' => THURSDAY, 'type' => ScheduleExceptionType::DayOff]);

        expect(localSlots($this->service, $this->staff))->toBe([]);
    });

    it('особые часы заменяют шаблон вместе с перерывами', function () {
        $staff = Staff::factory()->worksOn([4], '10:00', '19:00')->withBreak('12:00', '13:00', [4])->offers($this->service)->create();
        ScheduleException::create([
            'staff_id' => $staff->id, 'date' => THURSDAY, 'type' => ScheduleExceptionType::CustomHours,
            'starts_at' => '11:00', 'ends_at' => '14:00',
        ]);

        expect(localSlots($this->service, $staff))->toBe(['11:00', '12:00', '13:00']);
    });

    it('особые часы открывают обычный выходной', function () {
        ScheduleException::create([
            'staff_id' => $this->staff->id, 'date' => '2026-10-03', 'type' => ScheduleExceptionType::CustomHours,
            'starts_at' => '12:00', 'ends_at' => '14:00',
        ]);

        expect(localSlots($this->service, $this->staff, '2026-10-03'))->toBe(['12:00', '13:00']);
    });

    it('исключение на другую дату не влияет', function () {
        ScheduleException::create(['staff_id' => $this->staff->id, 'date' => '2026-10-02', 'type' => ScheduleExceptionType::DayOff]);

        expect(localSlots($this->service, $this->staff))->toHaveCount(5);
    });
});

describe('занятое время', function () {
    it('новые и подтверждённые записи занимают время', function (AppointmentStatus $status) {
        Appointment::factory()->for($this->staff)->at(moscow('11:00'))->status($status)->create();

        expect(localSlots($this->service, $this->staff))->toBe(['10:00', '12:00', '13:00', '14:00']);
    })->with([AppointmentStatus::New, AppointmentStatus::Confirmed]);

    it('отменённая запись освобождает время', function () {
        Appointment::factory()->for($this->staff)->at(moscow('11:00'))->status(AppointmentStatus::Cancelled)->create();

        expect(localSlots($this->service, $this->staff))->toHaveCount(5);
    });

    it('записи другого мастера не мешают', function () {
        Appointment::factory()->at(moscow('11:00'))->create();

        expect(localSlots($this->service, $this->staff))->toHaveCount(5);
    });

    it('учитывает буфер существующей записи', function () {
        config(['booking.slot_step' => 15]);
        Appointment::factory()->for($this->staff)->at(moscow('10:00'), 45)->create(['buffer_minutes' => 30]);

        // Мастер свободен с 11:15.
        expect(localSlots($this->service, $this->staff)[0])->toBe('11:15');
    });

    it('учитывает буфер новой услуги', function () {
        config(['booking.slot_step' => 15]);
        $service = Service::factory()->create(['duration_minutes' => 45, 'buffer_minutes' => 15]);
        $this->staff->services()->attach($service);
        Appointment::factory()->for($this->staff)->at(moscow('11:00'))->create();

        expect(localSlots($service, $this->staff))->toContain('10:00')->not->toContain('10:15');
    });

    it('при переносе не считает занятым время самой записи', function () {
        $appointment = Appointment::factory()->for($this->staff)->at(moscow('11:00'))->create();

        $slots = app(SlotService::class)->availableSlots($this->service, $this->staff, THURSDAY, ignoreAppointmentId: $appointment->id);

        expect($slots)->toHaveCount(5);
    });
});

describe('условия мастера', function () {
    it('берёт длительность услуги у конкретного мастера', function () {
        $staff = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service, ['duration_minutes' => 120])->create();

        expect(localSlots($this->service, $staff))->toBe(['10:00', '11:00', '12:00', '13:00']);
    });

    it('не даёт слотов, если мастер не делает услугу', function () {
        $other = Service::factory()->create();

        expect(localSlots($other, $this->staff))->toBe([]);
    });

    it('не даёт слотов у неактивного мастера или по неактивной услуге', function () {
        $this->staff->update(['is_active' => false]);
        expect(localSlots($this->service, $this->staff))->toBe([]);

        $this->staff->update(['is_active' => true]);
        $this->service->update(['is_active' => false]);
        expect(localSlots($this->service, $this->staff))->toBe([]);
    });
});

describe('текущее время и горизонт записи', function () {
    it('сегодня показывает слоты не раньше чем через минимальный запас', function () {
        $this->travelTo(moscow('10:30'));

        // Запас 60 минут: раньше 11:30 нельзя, по сетке ближайшее — 12:00.
        expect(localSlots($this->service, $this->staff))->toBe(['12:00', '13:00', '14:00']);
    });

    it('не даёт слотов на прошедшую дату', function () {
        expect(localSlots($this->service, $this->staff, '2026-09-29'))->toBe([]);
    });

    it('не даёт слотов дальше горизонта записи', function () {
        config(['booking.horizon_days' => 7]);

        // Сегодня 30.09 → открыто по 06.10 включительно; 07.10 — среда.
        expect(localSlots($this->service, $this->staff, '2026-10-06'))->not->toBe([])
            ->and(localSlots($this->service, $this->staff, '2026-10-07'))->toBe([]);
    });

    it('считает «сегодня» по поясу бизнеса, а не по UTC', function () {
        // 01.10 00:30 по Москве — в UTC ещё 30.09.
        $this->travelTo(moscow('00:30'));

        expect(app(SlotService::class)->bookableDates()[0])->toBe(THURSDAY);
    });
});

describe('проверка конкретного времени', function () {
    it('подтверждает свободное время по сетке', function () {
        expect(app(SlotService::class)->isAvailable($this->service, $this->staff, moscow('11:00')))->toBeTrue();
    });

    it('отклоняет время вне сетки и занятое', function () {
        Appointment::factory()->for($this->staff)->at(moscow('12:00'))->create();

        expect(app(SlotService::class)->isAvailable($this->service, $this->staff, moscow('11:20')))->toBeFalse()
            ->and(app(SlotService::class)->isAvailable($this->service, $this->staff, moscow('12:00')))->toBeFalse();
    });
});

describe('любой мастер', function () {
    it('объединяет слоты всех мастеров услуги', function () {
        $second = Staff::factory()->worksOn([4], '14:00', '17:00')->offers($this->service)->create();
        Staff::factory()->inactive()->worksOn([4], '17:00', '20:00')->offers($this->service)->create();

        $slots = app(SlotService::class)->availableSlotsForAnyStaff($this->service, THURSDAY);

        expect(collect($slots)->map(fn ($slot) => $slot['start']->setTimezone('Europe/Moscow')->format('H:i'))->values()->all())
            ->toBe(['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'])
            ->and($slots[moscow('14:00')->getTimestamp()]['staff']->pluck('id')->all())->toEqualCanonicalizing([$this->staff->id, $second->id]);
    });

    it('выбирает наименее загруженного в этот день мастера', function () {
        $second = Staff::factory()->worksOn([4], '10:00', '15:00')->offers($this->service)->create();
        Appointment::factory()->for($this->staff)->at(moscow('10:00'))->create();

        $chosen = app(SlotService::class)->leastBusyStaff(collect([$this->staff, $second]), THURSDAY);

        expect($chosen->id)->toBe($second->id);
    });
});
