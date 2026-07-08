<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database + file backups — enable on the server only.
// Also add `use Illuminate\Support\Facades\Schedule;` above, and a
// `* * * * * php artisan schedule:run` cron entry for it to actually fire.
// Schedule::command('backup:clean')->daily()->at('01:00');
// Schedule::command('backup:run')->daily()->at('01:30');
