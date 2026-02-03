<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Think Loop als Schedule (Alternative zum kontinuierlichen Worker)
Schedule::command('entity:think')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->runInBackground();
