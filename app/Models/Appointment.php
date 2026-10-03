<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property CarbonImmutable $starts_at UTC
 * @property CarbonImmutable $ends_at UTC
 * @property AppointmentStatus $status
 */
#[Fillable([
    'client_id', 'staff_id', 'service_id', 'starts_at', 'ends_at', 'price', 'buffer_minutes',
    'status', 'comment', 'confirmed_at', 'cancelled_at', 'cancel_reason',
    'reminder_day_sent_at', 'reminder_hours_sent_at',
])]
class Appointment extends Model implements Eventable
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->token ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reminder_day_sent_at' => 'immutable_datetime',
            'reminder_hours_sent_at' => 'immutable_datetime',
            'price' => 'integer',
            'buffer_minutes' => 'integer',
            'status' => AppointmentStatus::class,
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Записи, которые занимают время мастера.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::active());
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** Мастер свободен только после уборки и подготовки. */
    public function blockedUntil(): CarbonImmutable
    {
        return $this->ends_at->addMinutes($this->buffer_minutes);
    }

    public function localStartsAt(): CarbonImmutable
    {
        return BusinessTime::local($this->starts_at);
    }

    public function localEndsAt(): CarbonImmutable
    {
        return BusinessTime::local($this->ends_at);
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /** Клиент может отменить запись сам, пока она активна и до начала не меньше заданного запаса. */
    public function canBeCancelledByClient(?CarbonInterface $now = null): bool
    {
        return $this->status->isActive()
            && $this->starts_at->subMinutes(config('booking.client_change_deadline_minutes'))->greaterThanOrEqualTo($now ?? now());
    }

    /** Перенос из личного кабинета — по тем же правилам, что и отмена. */
    public function canBeRescheduledByClient(?CarbonInterface $now = null): bool
    {
        return $this->canBeCancelledByClient($now);
    }

    public function toCalendarEvent(): CalendarEvent
    {
        return CalendarEvent::make($this)
            ->title($this->client->name.' · '.$this->service->name)
            ->start($this->starts_at)
            ->end($this->ends_at)
            ->resourceId($this->staff_id)
            ->backgroundColor($this->status->hex())
            ->textColor('#ffffff')
            ->extendedProps([
                'status' => $this->status->getLabel(),
                'phone' => $this->client->formattedPhone(),
            ]);
    }
}
