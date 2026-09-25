<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Admin\NewAppointmentForAdmin;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentConfirmed;
use App\Services\Slots\SlotService;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Создаёт запись и не даёт двум клиентам занять одно время.
 *
 * MySQL не умеет запрещать пересечение интервалов на уровне схемы, поэтому
 * проверка и вставка идут в одной транзакции под блокировкой строки мастера:
 * второй запрос ждёт, пока первый закончит, и видит уже созданную запись.
 */
class BookAppointment
{
    public function __construct(
        private readonly SlotService $slots,
    ) {}

    /**
     * @throws SlotUnavailableException
     */
    public function handle(BookingRequest $request): Appointment
    {
        $service = Service::query()->where('is_active', true)->findOrFail($request->serviceId);
        $staffId = $request->staffId ?? $this->pickStaff($service, $request)->id;

        $appointment = DB::transaction(function () use ($service, $staffId, $request) {
            $staff = Staff::query()->whereKey($staffId)->lockForUpdate()->firstOrFail();

            if (! $this->slots->isAvailable($service, $staff, $request->startsAt)) {
                throw new SlotUnavailableException;
            }

            $offer = $staff->offer($service);

            $client = Client::query()->updateOrCreate(
                ['phone' => $request->phone],
                array_filter(['name' => $request->name, 'email' => $request->email]),
            );

            return Appointment::create([
                'client_id' => $client->id,
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'starts_at' => $request->startsAt,
                'ends_at' => $request->startsAt->addMinutes($offer->durationMinutes),
                'price' => $offer->price,
                'buffer_minutes' => $offer->bufferMinutes,
                'status' => $request->confirmed ? AppointmentStatus::Confirmed : AppointmentStatus::New,
                'confirmed_at' => $request->confirmed ? now() : null,
                'comment' => $request->comment,
            ]);
        });

        $appointment->client->notify($request->confirmed
            ? new AppointmentConfirmed($appointment)
            : new AppointmentBooked($appointment));

        if (! $request->fromAdmin) {
            Notification::send(User::all(), new NewAppointmentForAdmin($appointment));
        }

        return $appointment;
    }

    /** «Любой мастер»: из свободных в это время выбираем наименее загруженного. */
    private function pickStaff(Service $service, BookingRequest $request): Staff
    {
        $date = BusinessTime::local($request->startsAt)->toDateString();
        $slot = $this->slots->availableSlotsForAnyStaff($service, $date)[$request->startsAt->getTimestamp()] ?? null;

        if ($slot === null) {
            throw new SlotUnavailableException;
        }

        return $this->slots->leastBusyStaff($slot['staff'], $date);
    }
}
