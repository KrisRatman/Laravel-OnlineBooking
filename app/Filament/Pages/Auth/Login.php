<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * В демо-режиме форма входа заполнена демо-доступом, чтобы заказчик сразу попал в админку.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if (config('booking.demo.enabled')) {
            $this->form->fill([
                'email' => config('booking.demo.email'),
                'password' => config('booking.demo.password'),
                'remember' => true,
            ]);
        }
    }
}
