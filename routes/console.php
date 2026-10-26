<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal bersihkan log setiap Jumat jam 09:00 pagi
Schedule::command('log:clear')->fridays()->at('09:00');

// Sinkronisasi data wajah & akses UsersProfile ke gate port 787 setiap 5 menit
Schedule::command('users:sync-gate')->everyMinute();
// Schedule::command('users:sync-gate')->everyFiveMinutes();
