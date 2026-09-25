@php
    $groups = collect($this->availableTimes)->groupBy(function (string $time) {
        $hour = (int) substr($time, 0, 2);

        return $hour < 12 ? 'Утро' : ($hour < 17 ? 'День' : 'Вечер');
    }, preserveKeys: true);
@endphp

<div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
    <div class="min-w-0 space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">Онлайн-запись</h1>
            <p class="mt-1 text-stone-500">Выберите услугу, мастера и удобное время. Регистрация не нужна.</p>
        </div>

        {{-- Шаг 1. Услуга --}}
        <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <x-booking.step-heading number="1" title="Услуга" :done="$this->service !== null">
                @if ($this->service)
                    <button type="button" wire:click="resetService" class="text-sm font-semibold text-brand-700 hover:text-brand-900">Изменить</button>
                @endif
            </x-booking.step-heading>

            @if ($this->service)
                <div class="mt-3 flex items-baseline justify-between gap-4">
                    <div>
                        <div class="font-semibold">{{ $this->service->name }}</div>
                        <div class="text-sm text-stone-500">{{ $this->service->duration_minutes }} мин</div>
                    </div>
                    <div class="font-semibold text-stone-900">{{ $this->priceLabel }}</div>
                </div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse ($this->services as $service)
                        <button type="button" wire:key="service-{{ $service->id }}" wire:click="selectService({{ $service->id }})"
                                class="group rounded-xl border border-stone-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50">
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-semibold text-stone-900 group-hover:text-brand-800">{{ $service->name }}</span>
                                <span class="shrink-0 font-semibold">{{ money_rub($service->price) }}</span>
                            </div>
                            @if ($service->description)
                                <p class="mt-1 line-clamp-2 text-sm text-stone-500">{{ $service->description }}</p>
                            @endif
                            <div class="mt-2 text-xs font-medium text-stone-400">{{ $service->duration_minutes }} мин</div>
                        </button>
                    @empty
                        <p class="text-stone-500">Сейчас нет доступных услуг.</p>
                    @endforelse
                </div>
            @endif
        </section>

        {{-- Шаг 2. Мастер --}}
        @if ($this->service)
            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <x-booking.step-heading number="2" title="Мастер" :done="$staff !== null">
                    @if ($staff !== null && $this->staffList->count() > 1)
                        <button type="button" wire:click="resetStaff" class="text-sm font-semibold text-brand-700 hover:text-brand-900">Изменить</button>
                    @endif
                </x-booking.step-heading>

                @if ($staff !== null)
                    <div class="mt-3 flex items-center gap-3">
                        @if ($this->selectedStaff)
                            <x-booking.avatar :staff="$this->selectedStaff" />
                            <div>
                                <div class="font-semibold">{{ $this->selectedStaff->name }}</div>
                                <div class="text-sm text-stone-500">{{ $this->selectedStaff->position }}</div>
                            </div>
                        @else
                            <x-booking.avatar />
                            <div>
                                <div class="font-semibold">Любой свободный мастер</div>
                                <div class="text-sm text-stone-500">Подберём того, кто свободен в выбранное время</div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="selectStaff('any')"
                                class="flex items-center gap-3 rounded-xl border border-dashed border-stone-300 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50">
                            <x-booking.avatar />
                            <div>
                                <div class="font-semibold">Любой свободный мастер</div>
                                <div class="text-sm text-stone-500">Больше свободного времени</div>
                            </div>
                        </button>
                        @foreach ($this->staffList as $member)
                            <button type="button" wire:key="staff-{{ $member->id }}" wire:click="selectStaff('{{ $member->id }}')"
                                    class="flex items-center gap-3 rounded-xl border border-stone-200 p-4 text-left transition hover:border-brand-400 hover:bg-brand-50">
                                <x-booking.avatar :staff="$member" />
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold">{{ $member->name }}</div>
                                    <div class="text-sm text-stone-500">{{ $member->position }}</div>
                                </div>
                                <div class="shrink-0 text-sm font-semibold">{{ money_rub($member->offer($this->service)->price) }}</div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- Шаг 3. Дата и время --}}
        @if ($this->service && $staff !== null)
            <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <x-booking.step-heading number="3" title="Дата и время" :done="$startsAt !== null" />

                <div class="-mx-5 mt-4 overflow-x-auto px-5 pb-2">
                    <div class="flex gap-2">
                        @foreach ($this->dates as $day)
                            @php($d = \Carbon\CarbonImmutable::parse($day)->locale('ru'))
                            <button type="button" data-date="{{ $day }}" wire:key="date-{{ $day }}" wire:click="selectDate('{{ $day }}')"
                                    @class([
                                        'flex w-14 shrink-0 flex-col items-center rounded-xl border py-2 transition',
                                        'border-brand-600 bg-brand-600 text-white' => $day === $date,
                                        'border-stone-200 hover:border-brand-400' => $day !== $date,
                                        'text-rose-500' => $day !== $date && $d->isWeekend(),
                                    ])>
                                <span class="text-xs uppercase">{{ $d->isoFormat('dd') }}</span>
                                <span class="text-lg font-bold leading-tight">{{ $d->day }}</span>
                                <span @class(['text-[11px]', 'text-stone-400' => $day !== $date])>{{ $d->isoFormat('MMM') }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="relative mt-4 min-h-24">
                    <div wire:loading.flex wire:target="selectDate,selectStaff,goToNearestDate" class="absolute inset-0 z-10 items-center justify-center bg-white/70">
                        <span class="text-sm text-stone-500">Ищем свободное время…</span>
                    </div>

                    @error('startsAt')
                        <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
                    @enderror

                    @if ($groups->isEmpty())
                        <div class="rounded-xl bg-stone-50 px-4 py-6 text-center">
                            <p class="text-stone-600">На этот день свободного времени нет.</p>
                            @if ($nearest = $this->nearestAvailableDate())
                                <button type="button" wire:click="goToNearestDate" class="mt-3 inline-flex rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                                    Ближайшее свободное: {{ \Carbon\CarbonImmutable::parse($nearest)->locale('ru')->isoFormat('D MMMM, dd') }}
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($groups as $label => $times)
                                <div>
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-400">{{ $label }}</div>
                                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                        @foreach ($times as $timestamp => $time)
                                            <button type="button" data-slot="{{ $time }}" wire:key="slot-{{ $timestamp }}" wire:click="selectSlot({{ $timestamp }})"
                                                    @class([
                                                        'rounded-lg border py-2 text-sm font-semibold transition',
                                                        'border-brand-600 bg-brand-600 text-white' => $startsAt === $timestamp,
                                                        'border-stone-200 hover:border-brand-400 hover:bg-brand-50' => $startsAt !== $timestamp,
                                                    ])>
                                                {{ $time }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </div>

    {{-- Итог и контакты --}}
    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">Ваша запись</h2>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-stone-500">Услуга</dt>
                    <dd class="text-right font-medium">{{ $this->service?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-stone-500">Мастер</dt>
                    <dd class="text-right font-medium">
                        {{ $staff === null ? '—' : ($this->selectedStaff?->name ?? 'Любой свободный') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-stone-500">Когда</dt>
                    <dd class="text-right font-medium">{{ $selectedStart?->locale('ru')->isoFormat('D MMMM (dd), HH:mm') ?? '—' }}</dd>
                </div>
                @if ($this->service)
                    <div class="flex justify-between gap-4 border-t border-stone-100 pt-2">
                        <dt class="text-stone-500">Стоимость</dt>
                        <dd class="text-right font-bold text-stone-900">{{ $this->priceLabel }}</dd>
                    </div>
                @endif
            </dl>

            @if ($startsAt !== null)
                <form wire:submit="book" class="mt-5 space-y-3 border-t border-stone-100 pt-5">
                    <x-booking.field label="Имя" name="name" autocomplete="given-name" required />
                    <x-booking.field label="Телефон" name="phone" type="tel" autocomplete="tel" placeholder="+7 (999) 123-45-67" required />
                    <x-booking.field label="Email" name="email" type="email" autocomplete="email" hint="Пришлём подтверждение и напоминания" />
                    <x-booking.field label="Комментарий" name="comment" textarea />

                    @error('form')
                        <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</div>
                    @enderror

                    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-brand-600 px-4 py-3 font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="book">Записаться</span>
                        <span wire:loading wire:target="book">Записываем…</span>
                    </button>

                    <p class="text-center text-xs text-stone-400">Нажимая «Записаться», вы соглашаетесь на обработку персональных данных.</p>
                </form>
            @else
                <p class="mt-5 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-500">
                    Выберите услугу, мастера и время — затем оставьте имя и телефон.
                </p>
            @endif
        </div>
    </aside>
</div>
