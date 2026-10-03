<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\TelegramWebhookController;
use App\Livewire\BookingWizard;
use App\Livewire\Cabinet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', BookingWizard::class)->name('home');

Route::get('/booking/{appointment}', [BookingController::class, 'show'])->name('booking.show');
Route::post('/booking/{appointment}/cancel', [BookingController::class, 'cancel'])
    ->middleware('throttle:10,1')
    ->name('booking.cancel');

// Личный кабинет клиента.
Route::prefix('cabinet')->name('cabinet')->group(function () {
    Route::livewire('/login', Cabinet\Login::class)->middleware('guest:client')->name('.login');

    Route::middleware('auth:client')->group(function () {
        Route::livewire('/', Cabinet\Appointments::class)->name('');
        Route::livewire('/history', Cabinet\History::class)->name('.history');
        Route::livewire('/profile', Cabinet\Profile::class)->name('.profile');
        Route::livewire('/appointments/{appointment}/reschedule', Cabinet\Reschedule::class)->name('.reschedule');

        Route::post('/logout', function (Request $request) {
            Auth::guard('client')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('home');
        })->name('.logout');
    });
});

Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
