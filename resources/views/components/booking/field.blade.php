@props(['label', 'name', 'type' => 'text', 'textarea' => false, 'hint' => null])

@php
    $classes = 'mt-1 block w-full rounded-lg border px-3 py-2 text-sm shadow-sm outline-none transition focus:ring-2 '
        .($errors->has($name) ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-100' : 'border-stone-300 focus:border-brand-500 focus:ring-brand-100');
@endphp

<label class="block">
    <span class="text-sm font-medium text-stone-700">
        {{ $label }}
        @unless ($attributes->has('required'))
            <span class="font-normal text-stone-400">— необязательно</span>
        @endunless
    </span>

    @if ($textarea)
        <textarea wire:model="{{ $name }}" rows="2" {{ $attributes->merge(['class' => $classes]) }}></textarea>
    @else
        <input wire:model="{{ $name }}" type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    @endif

    @if ($errors->has($name))
        <span class="mt-1 block text-xs text-rose-600">{{ $errors->first($name) }}</span>
    @elseif ($hint)
        <span class="mt-1 block text-xs text-stone-400">{{ $hint }}</span>
    @endif
</label>
