@props(['dates', 'selected'])

{{-- Лента дат: клик вызывает selectDate('Y-m-d') у Livewire-компонента. --}}
<div class="-mx-5 overflow-x-auto px-5 pb-2">
    <div class="flex gap-2">
        @foreach ($dates as $day)
            @php($d = \Carbon\CarbonImmutable::parse($day)->locale('ru'))
            <button type="button" data-date="{{ $day }}" wire:key="date-{{ $day }}" wire:click="selectDate('{{ $day }}')"
                    @class([
                        'flex w-14 shrink-0 flex-col items-center rounded-xl border py-2 transition',
                        'border-brand-600 bg-brand-600 text-white' => $day === $selected,
                        'border-stone-200 hover:border-brand-400' => $day !== $selected,
                        'text-rose-500' => $day !== $selected && $d->isWeekend(),
                    ])>
                <span class="text-xs uppercase">{{ $d->isoFormat('dd') }}</span>
                <span class="text-lg font-bold leading-tight">{{ $d->day }}</span>
                <span @class(['text-[11px]', 'text-stone-400' => $day !== $selected])>{{ $d->isoFormat('MMM') }}</span>
            </button>
        @endforeach
    </div>
</div>
