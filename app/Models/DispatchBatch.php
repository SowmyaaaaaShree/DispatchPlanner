<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchBatch extends Model
{
    protected $fillable = ['processed_at', 'status'];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function dispatchRuns(): HasMany
    {
        return $this->hasMany(DispatchRun::class);
    }
}
