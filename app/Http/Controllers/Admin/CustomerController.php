<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use App\Exports\CustomerListExport;
use App\Exports\CustomerOrderExport;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SubscriberListExport;

class CustomerController extends Controller
{
    public function customer_list(Request $request)
    {
        $key = [];
        if($request->search)
        {
            $key = explode(' ', $request['search']);
        }
        $customers = User::
        when(count($key) > 0, function($query)use($key){
            foreach ($key as $value) {
                $query->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            };
        })
        ->orderBy('order_count','desc')->paginate(config('default_pagination'));
        return view('admin-views.customer.list', compact('customers'));
    }

    public function status(User $customer, Request $request)
    {
        $customer->status = $request->status;
        $customer->save();


        try {
            if($request->status == 0)
            {   $customer->tokens->each(function ($token, $key) {
                    $token->delete();
                });

                $notification_status= Helpers::getNotificationStatusData('customer','customer_account_block');

                if($notification_status?->push_notification_status  == 'active' && isset($customer->cm_firebase_token))
                {
                    $data = [
                        'title' => translate('messages.suspended'),
                        'description' => translate('messages.your_account_has_been_blocked'),
                        'order_id' => '',
                        'image' => '',
                        'type'=> 'block'
                    ];
                    Helpers::send_push_notif_to_device($customer->cm_firebase_token, $data);

                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'user_id'=>$customer->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }

                $mail_status = Helpers::get_mail_status('suspend_mail_status_user');
                if (  $notification_status?->mail_status == 'active' &&  config('mail.status') && $mail_status == '1') {
                    Mail::to( $customer->email)->send(new \App\Mail\UserStatus('suspended', $customer->f_name.' '.$customer->l_name));
                    }
                    }else{

                        $notification_status=null;
                        $notification_status= Helpers::getNotificationStatusData('customer','customer_account_unblock');

                        if($notification_status?->push_notification_status  == 'active' && isset($customer->cm_firebase_token))
                        {
                            $data = [
                                'title' => translate('messages.account_activation'),
                                'description' => translate('messages.your_account_has_been_activated'),
                                'order_id' => '',
                                'image' => '',
                                'type'=> 'unblock'
                            ];
                            Helpers::send_push_notif_to_device($customer->cm_firebase_token, $data);

                            DB::table('user_notifications')->insert([
                                'data'=> json_encode($data),
                                'user_id'=>$customer->id,
                                'created_at'=>now(),
                                'updated_at'=>now()
                            ]);
                        }


                $mail_status = Helpers::get_mail_status('unsuspend_mail_status_user');
                if (  $notification_status?->mail_status == 'active' &&  config('mail.status') && $mail_status == '1') {
                    Mail::to( $customer->email)->send(new \App\Mail\UserStatus('unsuspended', $customer->f_name.' '.$customer->l_name));
                }
            }
        } catch (\Exception $ex) {
            info($ex->getMessage());
        }

        Toastr::success(translate('messages.customer').translate('messages.status_updated'));
        return back();
    }

    public function view($id)
    {
        $key = explode(' ', request()?->search);
        $customer = User::find($id);
        if (isset($customer)) {
            $orders = Order::latest()->where(['user_id' => $id])->Notpos()->where('is_guest',0)
            ->when(isset($key), function($q) use($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->Where('id', 'like', "%{$value}%");
                    }
                });
            })
            ->paginate(config('default_pagination'));
            return view('admin-views.customer.customer-view', compact('customer', 'orders'));
        }
        Toastr::error(translate('messages.customer_not_found'));
        return back();
    }

    public function get_customers(Request $request){
        $key = explode(' ', $request['q']);
        $data = User::
        where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                ->orWhere('l_name', 'like', "%{$value}%")
                ->orWhere('phone', 'like', "%{$value}%");
            }
        })
        ->limit(10)
        ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')]);
        if($request->all) $data[]=(object)['id'=>false, 'text'=>translate('messages.all')];
        return response()->json($data);
    }

    public function settings()
    {
        $data = BusinessSetting::where('key','like','wallet_%')
            ->orWhere('key','like','loyalty_%')
            ->orWhere('key', 'like', 'customer_%')
            ->orWhere('key','like','ref_earning_%')
            ->orWhere('key','like','ref_earning_%')->get();
        $data = array_column($data->toArray(), 'value','key');
        return view('admin-views.customer.settings', compact('data'));
    }

    public function update_settings(Request $request)
    {

        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('messages.update_option_is_disable_for_demo'));
            return back();
        }

        $request->validate([
            'add_fund_bonus'=>'nullable|numeric|max:100|min:0',
            'loyalty_point_exchange_rate'=>'nullable|numeric',
            'ref_earning_exchange_rate'=>'nullable|numeric',
        ]);
        BusinessSetting::updateOrInsert(['key' => 'wallet_status'], [
            'value' => $request['customer_wallet']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_status'], [
            'value' => $request['customer_loyalty_point']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'ref_earning_status'], [
            'value' => $request['ref_earning_status'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'wallet_add_refund'], [
            'value' => $request['refund_to_wallet']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_exchange_rate'], [
            'value' => $request['loyalty_point_exchange_rate'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'ref_earning_exchange_rate'], [
            'value' => $request['ref_earning_exchange_rate'] ?? 0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_item_purchase_point'], [
            'value' => $request['item_purchase_point']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'loyalty_point_minimum_point'], [
            'value' => $request['minimun_transfer_point']??0
        ]);
        BusinessSetting::query()->updateOrInsert(['key' => 'customer_verification'], [
            'value' => $request['customer_verification']
        ]);
        BusinessSetting::updateOrInsert(['key' => 'add_fund_status'], [
            'value' => $request['add_fund_status']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_status'], [
            'value' => $request['new_customer_discount_status']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount'], [
            'value' => $request['new_customer_discount_amount']??0
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount_type'], [
            'value' => $request['new_customer_discount_amount_type']?? 'percentage'
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_amount_validity'], [
            'value' => $request['new_customer_discount_amount_validity']?? 1
        ]);
        BusinessSetting::updateOrInsert(['key' => 'new_customer_discount_validity_type'], [
            'value' => $request['new_customer_discount_validity_type']??'day'
        ]);
        Toastr::success(translate('messages.customer_settings_updated_successfully'));
        return back();
    }

    public function subscribedCustomers(Request $request)
    {
        $key = explode(' ', $request['search']);

        $subscribers = Newsletter::orderBy('id', 'desc')
        ->when(isset($key),function($q) use($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%". $value."%");
                }
            });
        })
        ->paginate(config('default_pagination'));
        return view('admin-views.customer.subscriber.list', compact('subscribers'));
    }

    public function export(Request $request){
        try{
                $key = [];
                if ($request->search) {
                    $key = explode(' ', $request['search']);
                }
                $customers = User::when(count($key) > 0, function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%");
                    };
                })
                ->orderBy('order_count', 'desc')->get();
                $data = [
                    'customers'=>$customers,
                    'search'=>$request->search??null,
                ];
                if ($request->type == 'excel') {
                    return Excel::download(new CustomerListExport($data), 'Customers.xlsx');
                } else if ($request->type == 'csv') {
                    return Excel::download(new CustomerListExport($data), 'Customers.csv');
                }
            }  catch(\Exception $e){
                Toastr::error("line___{$e->getLine()}",$e->getMessage());
                info(["line___{$e->getLine()}",$e->getMessage()]);
                return back();
            }
    }

    public function customer_order_export(Request $request)
    {
        try{
                $key = explode(' ', $request['search']);
                $customer = User::find($request->id);

                $orders = Order::latest()->where(['user_id' => $request->id])->Notpos()->where('is_guest',0)
                ->when(isset($key), function($q) use($key) {
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->Where('id', 'like', "%{$value}%");
                        }
                    });
                })
                ->get();
                $data = [
                    'orders'=>$orders,
                    'customer_id'=>$customer->id,
                    'customer_name'=>$customer->f_name.' '.$customer->l_name,
                    'customer_phone'=>$customer->phone,
                    'customer_email'=>$customer->email,
                ];
                if ($request->type == 'excel') {
                    return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.xlsx');
                } else if ($request->type == 'csv') {
                    return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.csv');
                }
            }  catch(\Exception $e){
                Toastr::error("line___{$e->getLine()}",$e->getMessage());
                info(["line___{$e->getLine()}",$e->getMessage()]);
                return back();
            }
    }

    public function subscribed_customer_export(Request $request){
        try{
            $key = explode(' ', $request['search']);
            $subscribers = Newsletter::orderBy('id', 'desc')
            ->when(isset($key),function($q) use($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('email', 'like', "%". $value."%");
                    }
                });
            })
            ->get();

            $data = [
                'customers'=>$subscribers
            ];

            if ($request->type == 'excel') {
                return Excel::download(new SubscriberListExport($data), 'Subscribers.xlsx');
            } else if ($request->type == 'csv') {
                return Excel::download(new SubscriberListExport($data), 'Subscribers.csv');
            }
        }  catch(\Exception $e){
            Toastr::error("line___{$e->getLine()}",$e->getMessage());
            info(["line___{$e->getLine()}",$e->getMessage()]);
            return back();
        }
    }

}
