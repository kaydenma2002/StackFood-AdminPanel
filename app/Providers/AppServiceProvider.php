<?php

namespace App\Providers;

use Exception;
use MeiliSearch\Client;
use App\Traits\AddonHelper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Sheet;
use App\CentralLogics\Helpers;
use App\Traits\ActivationClass;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redirect;
use App\Services\ExportService;

// ini_set('memory_limit', '512M');
ini_set("memory_limit",-1);
class AppServiceProvider extends ServiceProvider
{
    use ActivationClass,AddonHelper;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (!defined('DOMAIN_POINTED_DIRECTORY')) {
            define('DOMAIN_POINTED_DIRECTORY', 'public'); // or whatever value it should be
        }

    }

    /**
     * Bootstrap any application services.
     *
     */
    public function boot(Request $request)
    {
        // URL::forceScheme('https');
        if (($request->is('login/*') || $request->is('provider/auth/login')) && $request->isMethod('post')) {
            $response = $this->actch();
            $data = json_decode($response->getContent(), true);
            if (!$data['active']) {
                return Redirect::away(base64_decode('aHR0cHM6Ly9hY3RpdmF0aW9uLjZhbXRlY2guY29t'))->send();
            }
        }
        if (!App::runningInConsole()) {
            Config::set('addon_admin_routes',$this->get_addon_admin_routes());
            Config::set('get_payment_publish_status',$this->get_payment_publish_status());
            Config::set('default_pagination', 25);
            Paginator::useBootstrap();
            try {
                foreach(Helpers::get_view_keys() as $key=>$value)
                {
                    view()->share($key, $value);
                }
            } catch (\Exception $e){

            }
        }
        if (config('scout.driver') === 'meilisearch') {
            $client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

            $indexSettings = config('scout.meilisearch.index-settings');

            foreach ($indexSettings as $indexName => $settings) {
                $index = $client->index($indexName);
                $index->updateFilterableAttributes($settings['filterableAttributes']);
            }
        }
    }

}
