<?php

namespace App\Filament\Actions;

use App\Actions\AppointmentWorkflow;
use App\Enums\AppointmentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Filament\Support\SlotFields;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Действия с записью — одни и те же в таблице, карточке записи и календаре.
 */
class AppointmentActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::confirm(), self::complete(), self::reschedule(), self::cancel()];
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Подтвердить')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Appointment $record) => $record->status === AppointmentStatus::New)
            ->action(function (Appointment $record, AppointmentWorkflow $workflow) {
                $workflow->confirm($record);
                Notification::make()->title('Запись подтверждена, клиент получит уведомление')->success()->send();
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Выполнена')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('gray')
            ->visible(fn (Appointment $record) => $record->status->isActive() && $record->starts_at->isPast())
            ->action(function (Appointment $record, AppointmentWorkflow $workflow) {
                $workflow->complete($record);
                Notification::make()->title('Запись отмечена как выполненная')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Отменить')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Appointment $record) => $record->status->isActive())
            ->modalHeading('Отменить запись?')
            ->modalDescription('Клиент получит уведомление об отмене, а время снова станет свободным.')
            ->modalSubmitActionLabel('Отменить запись')
            ->schema([
                TextInput::make('reason')
                    ->label('Причина для клиента')
                    ->placeholder('Мастер заболел')
                    ->maxLength(255),
            ])
            ->action(function (Appointment $record, array $data, AppointmentWorkflow $workflow) {
                $workflow->cancel($record, $data['reason'] ?? null);
                Notification::make()->title('Запись отменена')->success()->send();
            });
    }

    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label('Перенести')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (Appointment $record) => $record->status->isActive())
            ->modalHeading('Перенос записи')
            ->modalDescription('Показано только свободное время выбранного мастера.')
            ->fillForm(fn (Appointment $record) => [
                'service_id' => $record->service_id,
                'staff_id' => $record->staff_id,
                'date' => $record->localStartsAt()->toDateString(),
            ])
            ->schema(fn (Appointment $record) => SlotFields::make(ignoreAppointmentId: $record->id, serviceLocked: true))
            ->action(function (Appointment $record, array $data, AppointmentWorkflow $workflow, Action $action) {
                try {
                    $workflow->reschedule($record, (int) $data['staff_id'], CarbonImmutable::createFromTimestampUTC((int) $data['slot']));
                } catch (SlotUnavailableException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                    $action->halt();
                }

                Notification::make()->title('Запись перенесена, клиент получит уведомление')->success()->send();
            });
    }
}
