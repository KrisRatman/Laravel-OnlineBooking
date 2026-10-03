<x-cabinet.page title="Мои записи" active="cabinet">
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-brand-600 p-5 text-white shadow-sm">
        <div>
            <div class="text-lg font-bold">Здравствуйте, {{ $client->name }}!</div>
            <div class="text-sm text-brand-50">
                @if ($this->visitsCount > 0)
                    Визитов в студию: {{ $this->visitsCount }}. Спасибо, что выбираете нас.
                @else
                    Рады видеть вас.
                @endif
            </div>
        </div>
        <a href="{{ route('home') }}" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-brand-700 hover:bg-brand-50">Записаться</a>
    </div>

    @forelse ($this->upcoming as $appointment)
        <x-cabinet.appointment-card :appointment="$appointment" wire:key="appointment-{{ $appointment->id }}">
            @if ($appointment->canBeCancelledByClient())
                <div x-data="{ cancelling: false }" class="mt-4 border-t border-stone-100 pt-4">
                    <div x-show="! cancelling" class="flex flex-wrap gap-2">
                        <a href="{{ route('cabinet.reschedule', $appointment) }}" wire:navigate
                           class="rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:border-brand-400 hover:text-brand-800">Перенести</a>
                        <button type="button" x-on:click="cancelling = true"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Отменить</button>
                        <a href="{{ route('booking.show', $appointment) }}" class="ml-auto self-center text-sm text-stone-500 hover:text-stone-800">Подробнее</a>
                    </div>

                    <form x-show="cancelling" x-cloak wire:submit="cancel({{ $appointment->id }})" class="space-y-3">
                        <label class="block">
                            <span class="text-sm font-medium text-stone-700">Причина <span class="font-normal text-stone-400">— необязательно</span></span>
                            <input wire:model="cancelReason" maxlength="255" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Да, отменить</button>
                            <button type="button" x-on:click="cancelling = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-stone-500 hover:text-stone-800">Не отменять</button>
                        </div>
                    </form>
                </div>
            @else
                <p class="mt-4 border-t border-stone-100 pt-4 text-sm text-stone-500">
                    До начала меньше {{ $deadlineMinutes }} мин — отменить или перенести запись можно по телефону
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $businessPhone) }}" class="whitespace-nowrap font-semibold text-brand-700">{{ $businessPhone }}</a>.
                </p>
            @endif
        </x-cabinet.appointment-card>
    @empty
        <div class="rounded-2xl border border-dashed border-stone-300 bg-white px-5 py-10 text-center">
            <p class="text-stone-600">Предстоящих записей нет.</p>
            <a href="{{ route('home') }}" class="mt-3 inline-flex rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Записаться</a>
        </div>
    @endforelse
</x-cabinet.page>
