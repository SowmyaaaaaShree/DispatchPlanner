<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DispatchRun extends Model
{
    protected $fillable = [
        'dispatch_batch_id',
        'city',
        'country',
        'dispatch_date',
        'total_invoiced_value_local',
        'total_invoiced_value_currency',
        'weather_summary',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'total_invoiced_value_local' => 'decimal:2',
        'weather_summary' => 'array',
    ];

    public function dispatchBatch(): BelongsTo
    {
        return $this->belongsTo(DispatchBatch::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_dispatch_run');
    }
}
