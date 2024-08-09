<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class Localization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        $lang = 'en';
        $direction = 'ltr';

        try {
            // Use caching to reduce duplicate queries
            $language = Cache::remember('system_language', 60 * 60, function () {
                return BusinessSetting::where('key', 'system_language')->first();
            });

            if ($language) {
                foreach (json_decode($language->value, true) as $data) {
                    if ($data['default'] == true) {
                        $lang = $data['code'];
                        $direction = $data['direction'];
                        break;
                    }
                }
            }
        } catch (\Exception $exception) {
            info($exception->getMessage());
        }

        if ($request->is('restaurant-panel*')) {
            if (session()->has('vendor_local')) {
                App::setLocale(session()->get('vendor_local'));
            } else {
                session()->put('vendor_site_direction', $direction);
                App::setLocale($lang);
            }
        } elseif ($request->is('admin*')) {
            if (session()->has('local')) {
                App::setLocale(session()->get('local'));
            } else {
                session()->put('site_direction', $direction);
                App::setLocale($lang);
            }
        } else {
            if (session()->has('landing_local')) {
                App::setLocale(session()->get('landing_local'));
            } else {
                session()->put('landing_site_direction', $direction);
                App::setLocale($lang);
            }
        }
        return $next($request);
    }
}
