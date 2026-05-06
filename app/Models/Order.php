<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Order extends Model
{
    protected $fillable = [
        'dispatch_batch_id',
        'order_id',
        'placed_at',
        'destination_address',
        'destination_country',
        'total_value',
        'total_value_currency',
        'weight_grams',
        'payment_mode',
        'geocoded_city',
        'geocoded_lat',
        'geocoded_lon',
        'planned_dispatch_date',
        'is_deferred',
        'deferred_reason',
        'invoiced_value_local',
        'invoiced_value_currency',
        'status',
        'error_message',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'planned_dispatch_date' => 'date',
        'total_value' => 'decimal:2',
        'geocoded_lat' => 'decimal:7',
        'geocoded_lon' => 'decimal:7',
        'invoiced_value_local' => 'decimal:2',
        'is_deferred' => 'boolean',
    ];

    public function dispatchBatch(): BelongsTo
    {
        return $this->belongsTo(DispatchBatch::class);
    }

    public function dispatchRuns(): BelongsToMany
    {
        return $this->belongsToMany(DispatchRun::class, 'order_dispatch_run');
    }
}
