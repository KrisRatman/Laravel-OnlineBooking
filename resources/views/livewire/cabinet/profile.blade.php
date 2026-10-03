<x-cabinet.page title="Профиль" active="cabinet.profile">
    <form wire:submit="save" class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Контакты</h2>

        <div>
            <div class="text-sm font-medium text-stone-700">Телефон</div>
            <div class="mt-1 font-semibold">{{ $client->formattedPhone() }}</div>
            <div class="mt-1 text-xs text-stone-400">По номеру мы узнаём вас при записи. Чтобы сменить его, позвоните в студию.</div>
        </div>

        <x-booking.field label="Имя" name="name" autocomplete="given-name" required />
        <x-booking.field label="Email" name="email" type="email" autocomplete="email" hint="Для подтверждений, напоминаний и кодов входа" />

        <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-700">Сохранить</button>
    </form>

    <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold">Telegram</h2>

        @if ($client->telegram_chat_id)
            <p class="mt-2 text-sm text-stone-600">Подключён: напоминания о записях и коды входа приходят в Telegram.</p>
            <button type="button" wire:click="disconnectTelegram" wire:confirm="Отключить напоминания в Telegram?"
                    class="mt-4 rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:border-rose-300 hover:text-rose-700">Отключить</button>
        @elseif ($telegramLink)
            <p class="mt-2 text-sm text-stone-600">Откройте бота и нажмите «Старт» — напомним о записи за сутки и за 2 часа, а коды входа будут приходить туда же.</p>
            <a href="{{ $telegramLink }}" target="_blank" rel="noopener"
               class="mt-4 inline-flex rounded-xl bg-sky-500 px-4 py-2 text-sm font-bold text-white hover:bg-sky-600">Подключить Telegram</a>
        @else
            <p class="mt-2 text-sm text-stone-500">Напоминания в Telegram сейчас недоступны.</p>
        @endif
    </div>
</x-cabinet.page>
