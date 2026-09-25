<?php

namespace App\Models;

use App\Services\Slots\ServiceOffer;
use Database\Factories\StaffFactory;
use Guava\Calendar\Contracts\Resourceable;
use Guava\Calendar\ValueObjects\CalendarResource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'position', 'bio', 'photo', 'is_active', 'sort'])]
class Staff extends Model implements Resourceable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory;

    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->using(ServiceStaff::class)
            ->withPivot(['price', 'duration_minutes']);
    }

    /** @return HasMany<WorkingHour, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class)->orderBy('weekday')->orderBy('starts_at');
    }

    /** @return HasMany<ScheduleBreak, $this> */
    public function breaks(): HasMany
    {
        return $this->hasMany(ScheduleBreak::class)->orderBy('weekday')->orderBy('starts_at');
    }

    /** @return HasMany<ScheduleException, $this> */
    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(ScheduleException::class)->orderBy('date');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @param Builder<Staff> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    /** Условия услуги у этого мастера (с учётом его цены и длительности) или null, если он её не делает. */
    public function offer(Service $service): ?ServiceOffer
    {
        $pivot = $this->relationLoaded('services')
            ? $this->services->firstWhere('id', $service->id)?->pivot
            : $this->services()->whereKey($service->id)->first()?->pivot;

        if ($pivot === null) {
            return null;
        }

        return new ServiceOffer(
            durationMinutes: $pivot->duration_minutes ?? $service->duration_minutes,
            bufferMinutes: $service->buffer_minutes,
            price: $pivot->price ?? $service->price,
        );
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function toCalendarResource(): CalendarResource
    {
        return CalendarResource::make($this->getKey())->title($this->name);
    }
}
