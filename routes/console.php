<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('booking:send-reminders')->everyFiveMinutes()->withoutOverlapping();

// На виртуальном хостинге нет постоянных процессов: очередь разбирает cron через планировщик.
if (config('booking.scheduled_queue_worker')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(5);
}
