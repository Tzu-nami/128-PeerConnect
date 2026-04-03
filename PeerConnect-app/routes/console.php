<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Bookings;

Schedule::call(function () {
    Bookings::where('booking_status', 'accepted')
        ->whereDate('date', '<', today())
        ->update([
            'booking_status' => 'completed',
            'completed_at'   => now(),
        ]);
})->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
