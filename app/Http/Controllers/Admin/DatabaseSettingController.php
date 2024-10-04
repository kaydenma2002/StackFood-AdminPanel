<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;


class DatabaseSettingController extends Controller
{
    public function db_index()
    {
        // Get all table names from the database
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();

        // List of tables to exclude from the count
        $filter_tables = array(
            'admin_roles',
            'admins',
            'business_settings',
            'colors',
            'currencies',
            'failed_jobs',
            'migrations',
            'oauth_access_tokens',
            'oauth_auth_codes',
            'oauth_clients',
            'oauth_personal_access_clients',
            'oauth_refresh_tokens',
            'password_resets',
            'personal_access_tokens',
            'phone_or_email_verifications',
            'social_medias',
            'soft_credentials',
            'users',
            'email_verifications',
            'phone_verifications',
            'phone_verifications',
            'restaurant_zone',
            'mail_configs',
            'translations',
            'vendor_employees',
            'telescope_entries' // Exclude this table
        );

        // Filter out the tables that are in the $filter_tables array
        $tables = array_values(array_diff($tables, $filter_tables));
        $rows = [];

        // Get the row count for each table that is not filtered
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            array_push($rows, $count);
        }

        // Return the view with the filtered tables and their row counts
        return view('admin-views.business-settings.db-index', compact('tables', 'rows'));
    }

    public function clean_db(Request $request)
    {
        $tables = (array)$request->tables;

        if (count($tables) == 0) {
            Toastr::error(translate('No Table Updated'));
            return back();
        }

        try {
            DB::transaction(function () use ($tables) {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            });
        } catch (\Exception $exception) {
            info($exception->getMessage());
            Toastr::error(translate('Failed to update!'));
            return back();
        }

        Toastr::success(translate('messages.updated_successfully'));
        return back();
    }
}
