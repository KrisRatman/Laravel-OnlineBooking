<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleExceptionType;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ScheduleBreak;
use App\Models\ScheduleException;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Notifications\Admin\NewAppointmentForAdmin;
use App\Services\Slots\Interval;
use App\Services\Slots\ServiceOffer;
use App\Services\Slots\SlotCalculator;
use App\Services\Slots\SlotService;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Демо-студия для портфолио: услуги, мастера с графиками и записи на две недели назад и вперёд.
 * Записи раскладываются тем же калькулятором слотов, поэтому пересечений в демо нет.
 */
class DemoSeeder extends Seeder
{
    private const SERVICES = [
        'men' => ['Мужская стрижка', 'Стрижка машинкой и ножницами, мытьё головы, укладка.', 45, 15, 150000],
        'women' => ['Женская стрижка', 'Консультация, мытьё, стрижка любой сложности и укладка.', 60, 15, 250000],
        'color' => ['Окрашивание в один тон', 'Профессиональные красители, уход после окрашивания.', 120, 15, 550000],
        'styling' => ['Укладка', 'Укладка феном или на плойку для повседневного образа или события.', 45, 0, 180000],
        'manicure' => ['Маникюр с покрытием', 'Аппаратный маникюр и покрытие гель-лаком.', 90, 15, 220000],
        'pedicure' => ['Педикюр', 'Аппаратный педикюр с покрытием гель-лаком.', 90, 15, 280000],
        'brows' => ['Оформление бровей', 'Коррекция формы и окрашивание.', 30, 0, 120000],
    ];

    private const CLIENTS = [
        'Ольга', 'Ирина', 'Светлана', 'Наталья', 'Алексей', 'Юлия', 'Татьяна', 'Сергей', 'Елена', 'Андрей',
        'Ксения', 'Павел', 'Виктория', 'Максим', 'Дарья', 'Александра', 'Никита', 'Полина', 'Артём', 'Вероника',
        'Марина', 'Игорь', 'Алина', 'Роман', 'Софья',
    ];

    public function run(): void
    {
        if (User::query()->where('email', config('booking.demo.email'))->exists()) {
            return;
        }

        mt_srand(42);

        $admin = User::create([
            'name' => 'Администратор',
            'email' => config('booking.demo.email'),
            'password' => config('booking.demo.password'),
        ]);

        $services = collect(self::SERVICES)->map(fn (array $s, string $key) => Service::create([
            'name' => $s[0], 'description' => $s[1], 'duration_minutes' => $s[2],
            'buffer_minutes' => $s[3], 'price' => $s[4], 'sort' => array_search($key, array_keys(self::SERVICES), true),
        ]));

        $staff = collect([
            $this->staff('Анна Соколова', 'Топ-стилист', 'Стрижки и окрашивание, 12 лет опыта.', [1, 2, 3, 4, 5], '10:00', '19:00', ['14:00', '15:00'],
                [[$services['women'], ['price' => 300000]], [$services['color'], []], [$services['styling'], []]]),
            $this->staff('Дмитрий Орлов', 'Барбер', 'Мужские стрижки и бороды.', [2, 3, 4, 5, 6], '11:00', '20:00', ['15:00', '15:30'],
                [[$services['men'], []], [$services['styling'], ['price' => 150000]]]),
            $this->staff('Екатерина Лебедева', 'Мастер ногтевого сервиса', 'Маникюр и педикюр, стерильные инструменты.', [1, 3, 5, 6, 7], '10:00', '18:00', ['13:00', '13:30'],
                [[$services['manicure'], []], [$services['pedicure'], []]]),
            $this->staff('Мария Кузнецова', 'Стилист и бровист', 'Брови, укладки, женские стрижки.', [4, 5, 6, 7], '12:00', '20:00', null,
                [[$services['brows'], []], [$services['styling'], []], [$services['women'], ['duration_minutes' => 75]]]),
        ]);

        $today = CarbonImmutable::parse(BusinessTime::today());

        // Анна на следующей неделе в среду учится, Дмитрий в ближайшую субботу работает до 16:00.
        ScheduleException::create([
            'staff_id' => $staff[0]->id, 'date' => $today->next(CarbonImmutable::WEDNESDAY)->addWeek()->toDateString(),
            'type' => ScheduleExceptionType::DayOff, 'note' => 'Повышение квалификации',
        ]);
        ScheduleException::create([
            'staff_id' => $staff[1]->id, 'date' => $today->next(CarbonImmutable::SATURDAY)->toDateString(),
            'type' => ScheduleExceptionType::CustomHours, 'starts_at' => '11:00', 'ends_at' => '16:00', 'note' => 'Короткий день',
        ]);

        // Первая клиентка — демо-доступ в личный кабинет («Войти как демо-клиент»).
        $clients = collect(self::CLIENTS)->map(fn (string $name, int $i) => Client::create([
            'name' => $name,
            'phone' => $i === 0
                ? config('booking.demo.client_phone')
                : '+7916'.str_pad((string) (1000000 + $i * 7919 % 9000000), 7, '0', STR_PAD_LEFT),
            'email' => match (true) {
                $i === 0 => 'olga@example.com',
                $i % 3 === 0 => null,
                default => 'client'.($i + 1).'@example.com',
            },
        ]));

        foreach (range(-14, 14) as $offset) {
            foreach ($staff as $member) {
                $this->fillDay($member, $today->addDays($offset), $clients->all());
            }
        }

        $this->giveDemoClientHistory($clients->first());

        // Пара свежих уведомлений в колокольчике админки.
        Appointment::query()->where('status', AppointmentStatus::New)->where('starts_at', '>', now())
            ->latest('starts_at')->take(3)->get()
            ->each(fn (Appointment $appointment) => $admin->notify(new NewAppointmentForAdmin($appointment)));
    }

    /**
     * @param  list<int>  $weekdays
     * @param  array{0: string, 1: string}|null  $break
     * @param  list<array{0: Service, 1: array<string, int>}>  $offers  [услуга, своя цена или длительность]
     */
    private function staff(string $name, string $position, string $bio, array $weekdays, string $from, string $to, ?array $break, array $offers): Staff
    {
        static $sort = 0;

        $member = Staff::create(['name' => $name, 'position' => $position, 'bio' => $bio, 'sort' => $sort++]);

        foreach ($offers as [$service, $pivot]) {
            $member->services()->attach($service, $pivot);
        }

        foreach ($weekdays as $weekday) {
            WorkingHour::create(['staff_id' => $member->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);

            if ($break) {
                ScheduleBreak::create(['staff_id' => $member->id, 'weekday' => $weekday, 'starts_at' => $break[0], 'ends_at' => $break[1]]);
            }
        }

        return $member;
    }

    /**
     * Клиенты раскиданы по записям случайно. Демо-клиентке отдаём несколько визитов в прошлом
     * и пару записей впереди (не раньше чем через сутки), чтобы в кабинете было что отменить и перенести.
     */
    private function giveDemoClientHistory(Client $demo): void
    {
        $completed = Appointment::query()->where('status', AppointmentStatus::Completed)->orderBy('starts_at')->get();
        $upcoming = Appointment::query()->active()->where('starts_at', '>', now()->addDay())->orderBy('starts_at')->get();

        $completed->nth(intdiv($completed->count(), 6) ?: 1)
            ->merge($upcoming->nth(intdiv($upcoming->count(), 2) ?: 1, 3)->take(2))
            ->each(fn (Appointment $appointment) => $appointment->update(['client_id' => $demo->id]));
    }

    /** @param list<Client> $clients */
    private function fillDay(Staff $staff, CarbonImmutable $date, array $clients): void
    {
        $day = app(SlotService::class)->daySchedule($staff, $date->toDateString());

        if ($day->isDayOff()) {
            return;
        }

        $offers = $staff->services()->get();
        $isPast = $date->isBefore(CarbonImmutable::parse(BusinessTime::today()));
        // Прошлое заполнено плотнее: так выглядит реальная загрузка.
        $fillChance = $isPast ? 70 : max(20, 65 - (int) abs($date->diffInDays(now())) * 3);
        $cursor = $day->workingIntervals()[0]->start;
        $busy = [];

        while (true) {
            $service = $offers->random();
            $offer = $staff->offer($service);

            $slots = (new SlotCalculator)->calculate($day, $offer->durationMinutes, $busy, 15, $offer->bufferMinutes, $cursor);

            if ($slots === []) {
                break;
            }

            $start = $slots[0];

            if (mt_rand(1, 100) > $fillChance) {
                $cursor = $start->addMinutes(mt_rand(2, 5) * 15);

                continue;
            }

            $appointment = $this->createAppointment($staff, $service, $offer, $start, $clients[array_rand($clients)]);
            $busy[] = new Interval($start, $appointment->blockedUntil());
            $cursor = $appointment->blockedUntil();
        }
    }

    private function createAppointment(Staff $staff, Service $service, ServiceOffer $offer, CarbonImmutable $start, Client $client): Appointment
    {
        $roll = mt_rand(1, 100);

        $status = match (true) {
            $start->isPast() => $roll <= 88 ? AppointmentStatus::Completed : AppointmentStatus::Cancelled,
            $start->isBefore(now()->addDays(2)) => $roll <= 75 ? AppointmentStatus::Confirmed : ($roll <= 92 ? AppointmentStatus::New : AppointmentStatus::Cancelled),
            default => $roll <= 45 ? AppointmentStatus::Confirmed : ($roll <= 93 ? AppointmentStatus::New : AppointmentStatus::Cancelled),
        };

        $createdAt = CarbonImmutable::parse(min($start->subHours(mt_rand(6, 240)), now()));

        $appointment = new Appointment([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($offer->durationMinutes),
            'price' => $offer->price,
            'buffer_minutes' => $offer->bufferMinutes,
            'status' => $status,
            'confirmed_at' => in_array($status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed], true) ? $createdAt->addHour() : null,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? $createdAt->addHours(2) : null,
            'cancel_reason' => $status === AppointmentStatus::Cancelled ? 'Отменена клиентом' : null,
        ]);
        $appointment->created_at = $createdAt;
        $appointment->updated_at = $createdAt;
        $appointment->save();

        return $appointment;
    }
}
