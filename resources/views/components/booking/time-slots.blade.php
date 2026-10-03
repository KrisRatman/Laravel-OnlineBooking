@props(['times', 'selected'])

{{-- Свободное время по частям дня: клик вызывает selectSlot(timestamp). $times: timestamp → «HH:MM». --}}
@php
    $groups = collect($times)->groupBy(function (string $time) {
        $hour = (int) substr($time, 0, 2);

        return $hour < 12 ? 'Утро' : ($hour < 17 ? 'День' : 'Вечер');
    }, preserveKeys: true);
@endphp

<div class="space-y-4">
    @foreach ($groups as $label => $group)
        <div>
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-400">{{ $label }}</div>
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                @foreach ($group as $timestamp => $time)
                    <button type="button" data-slot="{{ $time }}" wire:key="slot-{{ $timestamp }}" wire:click="selectSlot({{ $timestamp }})"
                            @class([
                                'rounded-lg border py-2 text-sm font-semibold transition',
                                'border-brand-600 bg-brand-600 text-white' => $selected === $timestamp,
                                'border-stone-200 hover:border-brand-400 hover:bg-brand-50' => $selected !== $timestamp,
                            ])>
                        {{ $time }}
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
