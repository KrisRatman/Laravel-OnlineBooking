@props(['appointment'])

@php($start = $appointment->localStartsAt()->locale('ru'))

<div {{ $attributes->class(['rounded-2xl border border-stone-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="text-sm font-semibold text-brand-700">
                {{ mb_ucfirst($start->isoFormat('dddd, D MMMM')) }}, {{ $start->format('H:i') }}–{{ $appointment->localEndsAt()->format('H:i') }}
            </div>
            <div class="mt-1 text-lg font-bold text-stone-900">{{ $appointment->service->name }}</div>
            <div class="text-sm text-stone-500">{{ $appointment->staff->name }} · {{ money_rub($appointment->price) }}</div>
        </div>
        <x-booking.status-badge :status="$appointment->status" />
    </div>

    {{ $slot }}
</div>
