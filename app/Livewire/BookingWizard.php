<?php

namespace App\Livewire;

use App\Actions\BookAppointment;
use App\Actions\BookingRequest;
use App\Exceptions\SlotUnavailableException;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Slots\SlotService;
use App\Support\BusinessTime;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Публичная запись без регистрации: услуга → мастер → дата и время → контакты.
 */
#[Layout('layouts.app')]
#[Title('Онлайн-запись')]
class BookingWizard extends Component
{
    public const ANY_STAFF = 'any';

    #[Url(as: 'service')]
    public ?int $serviceId = null;

    /** ID мастера или 'any'. */
    #[Url(as: 'master')]
    public ?string $staff = null;

    public ?string $date = null;

    /** Начало слота: unix timestamp (UTC). */
    public ?int $startsAt = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $comment = '';

    public function mount(): void
    {
        // Параметры из ссылки (?service=1&master=2) проверяем так же, как выбор на странице.
        if ($this->serviceId !== null && ! $this->services->contains('id', $this->serviceId)) {
            $this->serviceId = null;
        }

        if ($this->staff !== null && ($this->serviceId === null || ! $this->isValidStaff($this->staff))) {
            $this->staff = null;
        }

        if ($this->serviceId !== null && $this->staff === null && $this->staffList->count() === 1) {
            $this->staff = (string) $this->staffList->first()->id;
        }

        $this->date = $this->dates[0] ?? null;
    }

    public function selectService(int $serviceId): void
    {
        abort_unless($this->services->contains('id', $serviceId), 404);

        $this->serviceId = $serviceId;
        $this->staff = null;
        $this->startsAt = null;
        unset($this->staffList, $this->availableTimes);

        // Если услугу делает один мастер — выбирать нечего.
        if ($this->staffList->count() === 1) {
            $this->staff = (string) $this->staffList->first()->id;
        }
    }

    public function selectStaff(string $staff): void
    {
        abort_unless($this->isValidStaff($staff), 404);

        $this->staff = $staff;
        $this->startsAt = null;
        unset($this->availableTimes);
    }

    public function selectDate(string $date): void
    {
        abort_unless(in_array($date, $this->dates, true), 404);

        $this->date = $date;
        $this->startsAt = null;
        unset($this->availableTimes);
    }

    public function selectSlot(int $timestamp): void
    {
        abort_unless(array_key_exists($timestamp, $this->availableTimes), 404);

        $this->startsAt = $timestamp;
    }

    public function goToNearestDate(): void
    {
        if ($date = $this->nearestAvailableDate()) {
            $this->selectDate($date);
        }
    }

    public function resetService(): void
    {
        $this->reset('serviceId', 'staff', 'startsAt');
    }

    public function resetStaff(): void
    {
        $this->reset('staff', 'startsAt');
    }

    public function book(BookAppointment $book): mixed
    {
        $this->validate();

        $key = 'booking:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, config('booking.rate_limit'))) {
            $this->addError('form', 'Слишком много записей подряд. Попробуйте через несколько минут.');

            return null;
        }

        RateLimiter::hit($key, 600);

        try {
            $appointment = $book->handle(new BookingRequest(
                serviceId: $this->serviceId,
                staffId: $this->staff === self::ANY_STAFF ? null : (int) $this->staff,
                startsAt: CarbonImmutable::createFromTimestampUTC($this->startsAt),
                name: trim($this->name),
                phone: Phone::normalize($this->phone),
                email: filled($this->email) ? trim($this->email) : null,
                comment: filled($this->comment) ? trim($this->comment) : null,
            ));
        } catch (SlotUnavailableException $e) {
            $this->startsAt = null;
            unset($this->availableTimes);
            $this->addError('startsAt', $e->getMessage());

            return null;
        }

        session()->flash('booked', true);

        return $this->redirectRoute('booking.show', $appointment);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'serviceId' => ['required', 'integer'],
            'staff' => ['required', 'string'],
            'startsAt' => ['required', 'integer'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (Phone::normalize((string) $value) === null) {
                    $fail('Введите номер телефона в формате +7 (999) 123-45-67.');
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'serviceId' => 'услуга',
            'staff' => 'мастер',
            'startsAt' => 'время',
            'name' => 'имя',
            'phone' => 'телефон',
            'email' => 'email',
            'comment' => 'комментарий',
        ];
    }

    /** @return Collection<int, Service> */
    #[Computed]
    public function services(): Collection
    {
        return Service::query()->active()->whereHas('staff', fn ($query) => $query->where('is_active', true))->get();
    }

    #[Computed]
    public function service(): ?Service
    {
        return $this->services->firstWhere('id', $this->serviceId);
    }

    /** @return Collection<int, Staff> */
    #[Computed]
    public function staffList(): Collection
    {
        return $this->service ? app(SlotService::class)->staffFor($this->service) : collect();
    }

    #[Computed]
    public function selectedStaff(): ?Staff
    {
        return $this->staff === self::ANY_STAFF ? null : $this->staffList->firstWhere('id', (int) $this->staff);
    }

    /** @return list<string> */
    #[Computed]
    public function dates(): array
    {
        return app(SlotService::class)->bookableDates();
    }

    /**
     * Свободные слоты на выбранную дату: timestamp → местное время «HH:MM».
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableTimes(): array
    {
        if ($this->service === null || $this->staff === null || $this->date === null) {
            return [];
        }

        return collect($this->startsFor($this->date))
            ->mapWithKeys(fn (CarbonImmutable $start) => [$start->getTimestamp() => BusinessTime::local($start)->format('H:i')])
            ->all();
    }

    /** Итоговая цена: у конкретного мастера — его, у «любого» — диапазон. */
    #[Computed]
    public function priceLabel(): string
    {
        if ($this->service === null) {
            return '';
        }

        $prices = ($this->selectedStaff ? collect([$this->selectedStaff]) : $this->staffList)
            ->map(fn (Staff $staff) => $staff->offer($this->service)?->price)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($prices->count() > 1) {
            return 'от '.money_rub($prices->first());
        }

        return money_rub($prices->first() ?? $this->service->price);
    }

    public function nearestAvailableDate(): ?string
    {
        foreach ($this->dates as $date) {
            if ($date > $this->date && $this->startsFor($date) !== []) {
                return $date;
            }
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.booking-wizard', [
            'business' => config('booking.business'),
            'selectedStart' => $this->startsAt ? BusinessTime::local(CarbonImmutable::createFromTimestampUTC($this->startsAt)) : null,
        ]);
    }

    /** @return list<CarbonImmutable> */
    private function startsFor(string $date): array
    {
        $slots = app(SlotService::class);

        if ($this->staff === self::ANY_STAFF) {
            return array_column($slots->availableSlotsForAnyStaff($this->service, $date), 'start');
        }

        return $this->selectedStaff ? $slots->availableSlots($this->service, $this->selectedStaff, $date) : [];
    }

    private function isValidStaff(string $staff): bool
    {
        return $staff === self::ANY_STAFF
            ? $this->staffList->count() > 1
            : $this->staffList->contains('id', (int) $staff);
    }
}
