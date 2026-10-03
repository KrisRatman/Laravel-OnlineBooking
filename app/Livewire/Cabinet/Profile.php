<?php

namespace App\Livewire\Cabinet;

use App\Services\Telegram\TelegramBot;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Контакты клиента и подключение напоминаний в Telegram.
 */
#[Layout('layouts.app')]
#[Title('Профиль')]
class Profile extends CabinetPage
{
    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $this->name = $this->client()->name;
        $this->email = (string) $this->client()->email;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ], attributes: ['name' => 'имя', 'email' => 'email']);

        $this->client()->update([
            'name' => trim($data['name']),
            'email' => filled($data['email']) ? trim($data['email']) : null,
        ]);

        session()->now('status', 'Сохранено.');
    }

    public function disconnectTelegram(): void
    {
        $this->client()->update(['telegram_chat_id' => null]);

        session()->now('status', 'Telegram отключён. Напоминания будут приходить только на email.');
    }

    public function render(TelegramBot $bot): View
    {
        $client = $this->client();
        // Бот привязывает чат по токену любой записи клиента.
        $token = $client->appointments()->latest('starts_at')->value('token');

        return view('livewire.cabinet.profile', [
            'client' => $client,
            'telegramLink' => blank($client->telegram_chat_id) && $token ? $bot->startLink($token) : null,
        ]);
    }
}
