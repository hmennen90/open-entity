<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| For the think loop, use ONE of these approaches:
|
| 1. RECOMMENDED: Dedicated worker with adaptive intervals
|    docker-compose service: worker-think-loop
|    Command: php artisan entity:think --continuous --adaptive
|
| 2. ALTERNATIVE: Scheduler-based (fixed 30s interval, no adaptive)
|    Enable the Schedule::command below and disable worker-think-loop
|
*/

// Think Loop via Scheduler (DISABLED by default - use worker-think-loop instead)
// Uncomment if you prefer scheduler over dedicated worker:
// Schedule::command('entity:think')
//     ->everyThirtySeconds()
//     ->withoutOverlapping()
//     ->runInBackground();

// Memory consolidation (runs daily at 3 AM)
Schedule::command('entity:consolidate')
    ->dailyAt('03:00');
