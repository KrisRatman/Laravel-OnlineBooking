<?php

namespace App\Models;

use App\Enums\ScheduleExceptionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Исключение из расписания на конкретную дату: заменяет недельный шаблон и перерывы.
 */
#[Fillable(['staff_id', 'date', 'type', 'starts_at', 'ends_at', 'note'])]
class ScheduleException extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            // 'date' без cast: это календарная дата в поясе бизнеса (строка Y-m-d),
            // а не момент времени, и переводить её в UTC нельзя.
            'type' => ScheduleExceptionType::class,
        ];
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
