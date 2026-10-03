<?php

namespace App\Livewire\Cabinet;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

/**
 * История: прошедшие и отменённые записи, итоги визитов.
 */
#[Layout('layouts.app')]
#[Title('История визитов')]
class History extends CabinetPage
{
    use WithPagination;

    /** @return LengthAwarePaginator<int, Appointment> */
    #[Computed]
    public function appointments(): LengthAwarePaginator
    {
        return $this->client()->appointments()
            ->where(fn (Builder $query) => $query
                ->whereNotIn('status', AppointmentStatus::active())
                ->orWhere('ends_at', '<=', now()))
            ->with(['service', 'staff'])
            ->latest('starts_at')
            ->paginate(10);
    }

    /** @return array{visits: int, spent: int, favoriteStaff: ?string} */
    #[Computed]
    public function stats(): array
    {
        $completed = $this->client()->appointments()
            ->where('status', AppointmentStatus::Completed)
            ->with('staff')
            ->get(['id', 'staff_id', 'price']);

        return [
            'visits' => $completed->count(),
            'spent' => (int) $completed->sum('price'),
            'favoriteStaff' => $completed->groupBy('staff_id')
                ->sortByDesc(fn ($visits) => $visits->count())
                ->first()?->first()->staff->name,
        ];
    }

    public function render(): View
    {
        return view('livewire.cabinet.history');
    }
}
