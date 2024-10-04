<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateTelescopeEntries extends Command
{
    protected $signature = 'telescope:truncate-entries';
    protected $description = 'Truncate the telescope_entries table';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('telescope_entries_tags')->truncate();
        DB::table('telescope_entries')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Telescope entries table and related tags have been truncated successfully.');
    }
}
