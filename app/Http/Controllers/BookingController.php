<?php

namespace App\Http\Controllers;

use App\Actions\AppointmentWorkflow;
use App\Models\Appointment;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Страница записи для клиента. Доступ — по секретному токену из ссылки, без регистрации.
 */
class BookingController extends Controller
{
    public function show(Appointment $appointment, TelegramBot $bot): View
    {
        $appointment->load(['client', 'service', 'staff']);

        return view('booking.show', [
            'appointment' => $appointment,
            'telegramLink' => $appointment->status->isActive() && blank($appointment->client->telegram_chat_id)
                ? $bot->startLink($appointment->token)
                : null,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $appointment->canBeCancelledByClient()) {
            return back()->with('error', 'Эту запись уже нельзя отменить.');
        }

        $workflow->cancel($appointment, $data['reason'] ?? null, byClient: true);

        return redirect()->route('booking.show', $appointment)->with('status', 'Запись отменена.');
    }
}
