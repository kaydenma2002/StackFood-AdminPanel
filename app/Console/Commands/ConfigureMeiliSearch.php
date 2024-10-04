<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MeiliSearch\Client;

class ConfigureMeiliSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:configure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure MeiliSearch filterable attributes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $client = new Client('http://127.0.0.1:7700');

        $foodIndex = $client->index('food');
        $restaurantIndex = $client->index('restaurants');

        $foodIndex->updateFilterableAttributes([
            'active','status', 'zone_id','restaurant_id'
        ]);
        $foodIndex->updateSortableAttributes([
            'created_at','desc'
        ]);
        $foodIndex->updateSearchableAttributes(['name']);


        $restaurantIndex->updateFilterableAttributes([
            'active', 'zone_id', 'weekday','restaurant_id'
        ]);
        $restaurantIndex->updateSearchableAttributes(['name']);
        $restaurantIndex->updateSortableAttributes([
            'created_at',
            'desc',
            'open',        // Sorting by 'open' status
            'orders_count', // Sorting by number of orders
            'avg_rating',  // Sorting by average rating
            'reviews_count', // Sorting by number of reviews
            'distance',    // Sorting by distance if relevant
        ]);

        $this->info('MeiliSearch indexes configured successfully.');

        return 0;
    }
}

