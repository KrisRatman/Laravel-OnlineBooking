@props(['status'])

@php
    use App\Enums\AppointmentStatus;

    $classes = match ($status) {
        AppointmentStatus::New => 'bg-amber-100 text-amber-800',
        AppointmentStatus::Confirmed => 'bg-emerald-100 text-emerald-800',
        AppointmentStatus::Cancelled => 'bg-rose-100 text-rose-800',
        AppointmentStatus::Completed => 'bg-stone-100 text-stone-700',
    };
@endphp

<span {{ $attributes->class(['shrink-0 rounded-full px-3 py-1 text-xs font-semibold', $classes]) }}>{{ $status->getLabel() }}</span>
