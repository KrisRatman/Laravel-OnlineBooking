<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Рабочие часы мастера в определённый день недели (местное время бизнеса).
 */
#[Fillable(['staff_id', 'weekday', 'starts_at', 'ends_at'])]
class WorkingHour extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
