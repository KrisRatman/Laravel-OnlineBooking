@props(['title' => null])

@php($business = config('booking.business'))

<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ $business['name'] }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-stone-50 font-sans text-stone-800 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22V12" /><path d="M12 12C12 7 8 4 4 4c0 5 3 8 8 8Z" /><path d="M12 12c0-5 4-8 8-8 0 5-3 8-8 8Z" />
                    </svg>
                </span>
                <span>
                    <span class="block font-bold leading-tight">{{ $business['name'] }}</span>
                    <span class="block text-xs text-stone-500">{{ $business['address'] }}</span>
                </span>
            </a>
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $business['phone']) }}" class="hidden text-sm font-semibold text-brand-700 hover:text-brand-900 sm:block">
                {{ $business['phone'] }}
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:py-10">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-10 text-center text-xs text-stone-400">
        Время указано по часовому поясу студии ({{ now(config('booking.timezone'))->format('T, P') }}).
    </footer>

    @livewireScripts
</body>
</html>
