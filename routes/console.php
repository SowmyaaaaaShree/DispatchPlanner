<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('dispatch:process-daily', function () {
    // The command is already defined
})->purpose('Process daily dispatch batches')->dailyAt('06:00');
