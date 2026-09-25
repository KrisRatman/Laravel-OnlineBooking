<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-gray-500 dark:text-gray-400">
        @foreach ([\App\Enums\AppointmentStatus::New, \App\Enums\AppointmentStatus::Confirmed, \App\Enums\AppointmentStatus::Completed] as $status)
            <span class="inline-flex items-center gap-2">
                <span class="inline-block size-3 rounded-sm" style="background: {{ $status->hex() }}"></span>
                {{ $status->getLabel() }}
            </span>
        @endforeach
        <span>Время — {{ config('booking.timezone') }}. Перетащите запись, чтобы перенести её.</span>
    </div>

    @livewire($this->getCalendarWidget())
</x-filament-panels::page>
