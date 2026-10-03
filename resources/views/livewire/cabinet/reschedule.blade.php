<x-cabinet.page title="Перенос записи" active="cabinet">
    <x-cabinet.appointment-card :appointment="$this->appointment" />

    <section class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold">Новое время</h2>
        <p class="mt-1 text-sm text-stone-500">
            Свободное время мастера {{ $this->appointment->staff->name }}. Чтобы записаться к другому мастеру, отмените эту запись и запишитесь заново.
        </p>

        <div class="mt-4">
            <x-booking.date-strip :dates="$this->dates" :selected="$date" />
        </div>

        <div class="relative mt-4 min-h-24">
            <div wire:loading.flex wire:target="selectDate" class="absolute inset-0 z-10 items-center justify-center bg-white/70">
                <span class="text-sm text-stone-500">Ищем свободное время…</span>
            </div>

            @error('startsAt')
                <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
            @enderror

            @if ($this->availableTimes === [])
                <p class="rounded-xl bg-stone-50 px-4 py-6 text-center text-stone-600">На этот день свободного времени нет.</p>
            @else
                <x-booking.time-slots :times="$this->availableTimes" :selected="$startsAt" />
            @endif
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
        <div class="text-sm">
            <div class="text-stone-500">Новое время</div>
            <div class="font-bold">{{ $selectedStart ? mb_ucfirst($selectedStart->locale('ru')->isoFormat('dddd, D MMMM, HH:mm')) : '—' }}</div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('cabinet') }}" wire:navigate class="rounded-xl px-4 py-2.5 text-sm font-semibold text-stone-500 hover:text-stone-800">Назад</a>
            <button type="button" wire:click="reschedule" @disabled($startsAt === null)
                    class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50">Перенести</button>
        </div>
    </div>
</x-cabinet.page>
