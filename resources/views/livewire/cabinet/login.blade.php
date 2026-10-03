<div class="mx-auto max-w-md space-y-5">
    <div class="text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-stone-900 sm:text-3xl">Личный кабинет</h1>
        <p class="mt-1 text-stone-500">Ваши записи, история визитов и настройки напоминаний.</p>
    </div>

    <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
        @if ($codeSentTo === null)
            <form wire:submit="sendCode" class="space-y-4">
                <x-booking.field label="Телефон" name="phone" type="tel" autocomplete="tel" placeholder="+7 (999) 123-45-67"
                                 hint="Номер, который вы указывали при записи" required autofocus />

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-brand-600 px-4 py-3 font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="sendCode">Получить код</span>
                    <span wire:loading wire:target="sendCode">Отправляем…</span>
                </button>
            </form>
        @else
            <form wire:submit="verify" class="space-y-4">
                <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">
                    Если вы уже записывались к нам с номером <b class="whitespace-nowrap">{{ \App\Support\Phone::format($codeSentTo) }}</b>,
                    код придёт в Telegram и на email, которые привязаны к этому номеру.
                </p>

                <x-booking.field label="Код из сообщения" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                                 placeholder="000000" class="text-center text-lg tracking-[0.5em]" required autofocus />

                <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-brand-600 px-4 py-3 font-bold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="verify">Войти</span>
                    <span wire:loading wire:target="verify">Проверяем…</span>
                </button>

                <div class="flex justify-between gap-4 text-sm">
                    <button type="button" wire:click="changePhone" class="font-semibold text-stone-500 hover:text-stone-800">Другой номер</button>
                    <button type="button" wire:click="sendCode" class="font-semibold text-brand-700 hover:text-brand-900">Отправить ещё раз</button>
                </div>
            </form>

            <details class="mt-5 border-t border-stone-100 pt-4 text-sm text-stone-500">
                <summary class="cursor-pointer font-medium text-stone-600">Код не пришёл?</summary>
                <p class="mt-2">
                    Код отправляется только в Telegram и на email, которые вы подключили при записи.
                    Если их нет — позвоните нам по номеру
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $businessPhone) }}" class="whitespace-nowrap font-semibold text-brand-700">{{ $businessPhone }}</a>,
                    или откройте ссылку на запись из сообщения: там можно подключить Telegram.
                </p>
            </details>
        @endif
    </div>

    @if ($demo)
        <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-5 text-center">
            <p class="text-sm text-amber-900">Это демо: войдите постоянным клиентом студии, чтобы посмотреть кабинет с историей визитов.</p>
            <button type="button" wire:click="loginAsDemo" class="mt-3 rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white hover:bg-amber-600">
                Войти как демо-клиент
            </button>
        </div>
    @endif
</div>
