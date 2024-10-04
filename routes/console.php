<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Artisan::command('telescope:truncate', function () {
    // Disable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

    // Truncate the tables
    DB::table('telescope_entries_tags')->truncate();
    DB::table('telescope_entries')->truncate();

    // Re-enable foreign key checks
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    $this->comment('Telescope entries and related tags have been truncated.');
})->describe('Truncate the telescope_entries and telescope_entries_tags tables');
