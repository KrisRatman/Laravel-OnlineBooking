@props(['staff' => null])

@if ($staff?->photoUrl())
    <img src="{{ $staff->photoUrl() }}" alt="{{ $staff->name }}" class="size-12 shrink-0 rounded-full object-cover">
@elseif ($staff)
    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brand-100 font-bold text-brand-800">{{ $staff->initials() }}</span>
@else
    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-stone-100 text-stone-500">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72M18 18.72v.03c0 .22-.01.45-.04.67A11.9 11.9 0 0 1 12 21c-2.17 0-4.2-.58-5.96-1.58a6 6 0 0 1-.04-.7m12 0a5.97 5.97 0 0 0-.94-3.2M6.06 18.72a3 3 0 0 1-4.68-2.72 9.1 9.1 0 0 0 3.74.48m.94 2.24a5.97 5.97 0 0 1 .94-3.2m0 0A6 6 0 0 1 12 12.75a6 6 0 0 1 5.06 2.77M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
        </svg>
    </span>
@endif
