<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Think Loop as Schedule (alternative to continuous worker)
Schedule::command('entity:think')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('entity:consolidate')
    ->dailyAt('03:00');
