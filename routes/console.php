<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('gymie.schedule_default_commands', true)) {
    // Mark subscriptions expired every day at 00:00
    Schedule::command('gymie:subscriptions')
        ->name('gymie-subscriptions')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');

    // Mark invoices overdue every day at 00:00
    Schedule::command('gymie:invoices --mark-overdue')
        ->name('gymie-invoices-overdue')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');

    Schedule::command('gymie:expenses')
        ->name('gymie-expenses-overdue')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('00:00');

    Schedule::command('gymie:inactive-members')
        ->name('gymie-inactive-members')
        ->withoutOverlapping(30)
        ->onOneServer()
        ->dailyAt('09:00');
}
