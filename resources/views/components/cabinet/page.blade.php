@props(['title', 'active'])

{{-- Обёртка страниц кабинета: заголовок, вкладки, сообщения. --}}
<div class="mx-auto max-w-3xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-sm text-stone-500">Личный кабинет</div>
            <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">{{ $title }}</h1>
        </div>
        <form method="POST" action="{{ route('cabinet.logout') }}">
            @csrf
            <button type="submit" class="text-sm font-semibold text-stone-500 hover:text-stone-800">Выйти</button>
        </form>
    </div>

    <nav class="flex gap-1 overflow-x-auto rounded-xl bg-stone-100 p-1 text-sm font-semibold">
        @foreach (['cabinet' => 'Мои записи', 'cabinet.history' => 'История', 'cabinet.profile' => 'Профиль'] as $route => $label)
            <a href="{{ route($route) }}" wire:navigate
               @class([
                   'flex-1 whitespace-nowrap rounded-lg px-4 py-2 text-center transition',
                   'bg-white text-stone-900 shadow-sm' => $route === $active,
                   'text-stone-500 hover:text-stone-800' => $route !== $active,
               ])>{{ $label }}</a>
        @endforeach
    </nav>

    @if (session('status'))
        <div class="rounded-xl bg-stone-800 px-4 py-3 text-sm text-white">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{ $slot }}
</div>
