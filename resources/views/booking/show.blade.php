@php
    use App\Enums\AppointmentStatus;

    $start = $appointment->localStartsAt()->locale('ru');
@endphp

<x-layout title="Ваша запись">
    <div class="mx-auto max-w-xl space-y-5">
        @if (session('booked'))
            <div class="rounded-2xl bg-brand-600 p-5 text-white shadow-sm">
                <div class="text-lg font-bold">Вы записаны!</div>
                <p class="mt-1 text-brand-50">
                    Сохраните эту страницу: по ссылке можно посмотреть или отменить запись.
                    @if ($appointment->client->email)
                        Копия отправлена на {{ $appointment->client->email }}.
                    @endif
                </p>
            </div>
        @endif

        @if (session('status'))
            <div class="rounded-xl bg-stone-800 px-4 py-3 text-sm text-white">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
        @endif

        <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm text-stone-500">Запись</div>
                    <h1 class="text-2xl font-extrabold tracking-tight">{{ $appointment->service->name }}</h1>
                </div>
                <x-booking.status-badge :status="$appointment->status" />
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-stone-500">Дата</dt>
                    <dd class="font-semibold">{{ mb_ucfirst($start->isoFormat('dddd, D MMMM')) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-stone-500">Время</dt>
                    <dd class="font-semibold">{{ $start->format('H:i') }}–{{ $appointment->localEndsAt()->format('H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-stone-500">Мастер</dt>
                    <dd class="font-semibold">{{ $appointment->staff->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-stone-500">Стоимость</dt>
                    <dd class="font-semibold">{{ money_rub($appointment->price) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-stone-500">Адрес</dt>
                    <dd class="font-semibold">{{ config('booking.business.address') }}</dd>
                </div>
            </dl>

            @if ($appointment->status === AppointmentStatus::Cancelled && $appointment->cancel_reason)
                <p class="mt-5 rounded-lg bg-stone-50 px-4 py-3 text-sm text-stone-600">Причина отмены: {{ $appointment->cancel_reason }}</p>
            @endif
        </div>

        @if ($telegramLink)
            <a href="{{ $telegramLink }}" target="_blank" rel="noopener"
               class="flex items-center gap-4 rounded-2xl border border-sky-200 bg-sky-50 p-5 transition hover:bg-sky-100">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-sky-500 text-white">
                    <svg class="size-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.78 18.65 10.06 14.4l7.72-6.96c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42Z" /></svg>
                </span>
                <span>
                    <span class="block font-bold text-sky-900">Напоминания в Telegram</span>
                    <span class="block text-sm text-sky-800">Откройте бота и нажмите «Старт» — напомним за сутки и за 2 часа.</span>
                </span>
            </a>
        @endif

        @if ($appointment->canBeCancelledByClient())
            <details class="group rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                <summary class="cursor-pointer list-none font-semibold text-rose-700">Отменить запись</summary>
                <form method="POST" action="{{ route('booking.cancel', $appointment) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="block">
                        <span class="text-sm font-medium text-stone-700">Причина <span class="font-normal text-stone-400">— необязательно</span></span>
                        <input name="reason" maxlength="255" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </label>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Да, отменить</button>
                </form>
            </details>
        @endif

        <div class="flex justify-center gap-6 text-sm font-semibold">
            <a href="{{ route('home') }}" class="text-brand-700 hover:text-brand-900">Записаться ещё</a>
            <a href="{{ route('cabinet') }}" class="text-brand-700 hover:text-brand-900">Все мои записи</a>
        </div>
    </div>
</x-layout>
