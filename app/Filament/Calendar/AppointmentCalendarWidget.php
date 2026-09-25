<?php

namespace App\Filament\Calendar;

use App\Actions\AppointmentWorkflow;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotUnavailableException;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\EventClickInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Календарь записей: колонка на каждого мастера. Клик — карточка записи,
 * перетаскивание — перенос с той же проверкой свободного времени, что и везде.
 */
class AppointmentCalendarWidget extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::ResourceTimeGridDay;

    // Календарь показывает время в поясе бизнеса, а не в поясе браузера администратора.
    protected bool $useFilamentTimezone = true;

    protected bool $eventClickEnabled = true;

    protected bool $eventDragEnabled = true;

    protected ?string $locale = 'ru';

    protected string|\Illuminate\Support\HtmlString|bool|null $heading = 'Расписание мастеров';

    public function getOptions(): array
    {
        return [
            'headerToolbar' => [
                'start' => 'prev,next today',
                'center' => 'title',
                'end' => 'resourceTimeGridDay,timeGridWeek,dayGridMonth,listWeek',
            ],
            'buttonText' => [
                'today' => 'Сегодня',
                'resourceTimeGridDay' => 'День',
                'timeGridWeek' => 'Неделя',
                'dayGridMonth' => 'Месяц',
                'listWeek' => 'Список',
            ],
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '22:00:00',
            'slotDuration' => '00:15:00',
            'slotLabelInterval' => '01:00',
            'scrollTime' => '09:00:00',
            'allDaySlot' => false,
            'height' => '780px',
            'eventTimeFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'slotLabelFormat' => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
        ];
    }

    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return Appointment::query()
            ->with(['client', 'service'])
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->where('starts_at', '<', $info->end)
            ->where('ends_at', '>', $info->start);
    }

    protected function getResources(): Collection|array|Builder
    {
        return Staff::query()->active();
    }

    protected function onEventClick(EventClickInfo $info, Model $event, ?string $action = null): void
    {
        $this->redirect(AppointmentResource::getUrl('view', ['record' => $event]));
    }

    protected function onEventDrop(EventDropInfo $info, Model $event): bool
    {
        /** @var Appointment $event */
        $staffId = (int) ($this->getRawCalendarContextData('newResource.id') ?? $event->staff_id);
        $startsAt = CarbonImmutable::instance($info->event->getStart())->utc();

        try {
            app(AppointmentWorkflow::class)->reschedule($event, $staffId, $startsAt);
        } catch (SlotUnavailableException|InvalidStatusTransitionException) {
            Notification::make()
                ->title('Не получилось перенести')
                ->body('Это время занято, мастер не работает или не делает эту услугу.')
                ->danger()
                ->send();

            return false;
        }

        Notification::make()->title('Запись перенесена, клиент получит уведомление')->success()->send();

        return true;
    }
}
