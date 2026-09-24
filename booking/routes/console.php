<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('hotel:status', function () {
    $this->info('Hotel Vastu booking application is ready.');
});
