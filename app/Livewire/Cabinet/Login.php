<?php

namespace App\Livewire\Cabinet;

use App\Models\Client;
use App\Services\ClientAuth\LoginCodes;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Вход в личный кабинет: телефон → код из Telegram или письма.
 */
#[Layout('layouts.app')]
#[Title('Вход в личный кабинет')]
class Login extends Component
{
    public string $phone = '';

    public string $code = '';

    /** Нормализованный номер, на который запрошен код. */
    #[Locked]
    public ?string $codeSentTo = null;

    public function sendCode(LoginCodes $codes): void
    {
        $this->validate(['phone' => ['required', 'string']], attributes: ['phone' => 'телефон']);

        $phone = Phone::normalize($this->phone);

        if ($phone === null) {
            $this->addError('phone', 'Введите номер телефона в формате +7 (999) 123-45-67.');

            return;
        }

        if (! $this->hitLimit('cabinet-code-ip:'.request()->ip(), 10)
            || ! $this->hitLimit('cabinet-code-phone:'.$phone, config('booking.login_code.per_phone'))) {
            $this->addError('phone', 'Слишком много запросов кода. Попробуйте через несколько минут.');

            return;
        }

        // Результат намеренно не показываем: ответ одинаковый для любого номера.
        $codes->send($phone);

        $this->codeSentTo = $phone;
        $this->code = '';
        $this->resetErrorBag();
    }

    public function verify(LoginCodes $codes): void
    {
        if ($this->codeSentTo === null) {
            return;
        }

        $this->validate(['code' => ['required', 'digits:6']], attributes: ['code' => 'код']);

        if (! $this->hitLimit('cabinet-verify-ip:'.request()->ip(), 30)) {
            $this->addError('code', 'Слишком много попыток. Попробуйте через несколько минут.');

            return;
        }

        $client = $codes->verify($this->codeSentTo, $this->code);

        if ($client === null) {
            $this->addError('code', 'Неверный или устаревший код. Запросите новый.');

            return;
        }

        $this->loginAs($client);
    }

    public function changePhone(): void
    {
        $this->reset('codeSentTo', 'code');
        $this->resetErrorBag();
    }

    /** Демо для портфолио: войти демо-клиентом без кода. */
    public function loginAsDemo(): void
    {
        abort_unless(config('booking.demo.enabled'), 404);

        $this->loginAs(Client::query()->where('phone', config('booking.demo.client_phone'))->firstOrFail());
    }

    public function render(): View
    {
        return view('livewire.cabinet.login', [
            'demo' => config('booking.demo.enabled'),
            'businessPhone' => config('booking.business.phone'),
        ]);
    }

    private function loginAs(Client $client): void
    {
        Auth::guard('client')->login($client, remember: true);
        session()->regenerate();

        $this->redirectIntended(route('cabinet'));
    }

    /** Засчитывает попытку на 10 минут. false — лимит исчерпан. */
    private function hitLimit(string $key, int $maxAttempts): bool
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, 600);

        return true;
    }
}
