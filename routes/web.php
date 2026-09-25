<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\TelegramWebhookController;
use App\Livewire\BookingWizard;
use Illuminate\Support\Facades\Route;

Route::livewire('/', BookingWizard::class)->name('home');

Route::get('/booking/{appointment}', [BookingController::class, 'show'])->name('booking.show');
Route::post('/booking/{appointment}/cancel', [BookingController::class, 'cancel'])
    ->middleware('throttle:10,1')
    ->name('booking.cancel');

Route::post('/telegram/webhook', TelegramWebhookController::class)->name('telegram.webhook');
