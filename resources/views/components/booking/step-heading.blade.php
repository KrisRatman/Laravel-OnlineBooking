@props(['number', 'title', 'done' => false])

<div class="flex items-center justify-between gap-4">
    <h2 class="flex items-center gap-3 text-lg font-bold">
        <span @class([
            'grid size-7 place-items-center rounded-full text-sm',
            'bg-brand-600 text-white' => $done,
            'bg-stone-100 text-stone-500' => ! $done,
        ])>
            @if ($done)
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
            @else
                {{ $number }}
            @endif
        </span>
        {{ $title }}
    </h2>
    {{ $slot }}
</div>
