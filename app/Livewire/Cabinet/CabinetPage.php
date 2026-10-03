<?php

namespace App\Livewire\Cabinet;

use App\Models\Appointment;
use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Страница личного кабинета. Маршруты закрыты middleware auth:client.
 */
abstract class CabinetPage extends Component
{
    protected function client(): Client
    {
        /** @var Client */
        return Auth::guard('client')->user();
    }

    /** Запись этого клиента: чужая для него не существует. */
    protected function findAppointment(int $id): Appointment
    {
        return $this->client()->appointments()->findOrFail($id);
    }
}
