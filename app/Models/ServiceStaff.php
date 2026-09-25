<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Услуга в исполнении конкретного мастера: может переопределять цену и длительность.
 */
class ServiceStaff extends Pivot
{
    protected $table = 'service_staff';

    public $incrementing = true;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }
}
