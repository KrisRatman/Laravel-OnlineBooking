<x-cabinet.page title="История визитов" active="cabinet.history">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-stone-500">Визитов</div>
            <div class="mt-1 text-2xl font-extrabold">{{ $this->stats['visits'] }}</div>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-stone-500">На сумму</div>
            <div class="mt-1 text-2xl font-extrabold">{{ money_rub($this->stats['spent']) }}</div>
        </div>
        <div class="rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="text-sm text-stone-500">Ваш мастер</div>
            <div class="mt-1 truncate text-lg font-bold">{{ $this->stats['favoriteStaff'] ?? '—' }}</div>
        </div>
    </div>

    @forelse ($this->appointments as $appointment)
        <x-cabinet.appointment-card :appointment="$appointment" wire:key="history-{{ $appointment->id }}">
            @if ($appointment->status === \App\Enums\AppointmentStatus::Cancelled && $appointment->cancel_reason)
                <p class="mt-3 text-sm text-stone-500">Причина отмены: {{ $appointment->cancel_reason }}</p>
            @endif
            <div class="mt-4 border-t border-stone-100 pt-4">
                <a href="{{ route('home', ['service' => $appointment->service_id, 'master' => $appointment->staff_id]) }}"
                   class="text-sm font-semibold text-brand-700 hover:text-brand-900">Записаться снова →</a>
            </div>
        </x-cabinet.appointment-card>
    @empty
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-5 py-10 text-center text-stone-600">
            Здесь появятся ваши прошедшие визиты.
        </div>
    @endforelse

    {{ $this->appointments->links() }}
</x-cabinet.page>
