<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WhatsappController extends Controller
{
    public function whatsapp()
    {
    return view('admin-views.business-settings.whatsapp-index');
    }

    public function whatsappIndex()
    {
    return view('admin-views.business-settings.whatsapp-index');
    }

    public function whatsappStore(Request $request)
    {

        $count = DB::table('whatsapp_setting')->count();
        if ($count == 0) {
        DB::table('whatsapp_setting')->insert([
        'url' => $request->input('url'),
        'instance_id' => $request->input('instance_id'),
        'access_token' => $request->input('access_token'),
        'message_type' => $request->input('message_type'),
        'message_otp' => $request->input('message_otp'),
        'message_new_order' => $request->input('message_new_order'),
        'message_order_confirm' => $request->input('message_confirm'),
        'message_order_cancel' => $request->input('message_cancel'),
        'message_order_processing' => $request->input('message_process'),
        'message_order_handover' => $request->input('message_handover'),
        'message_order_pickup' => $request->input('message_pickup'),
        'message_order_delivered' => $request->input('message_delivered'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Toastr::success(translate('messages.Whatsapp_Setting_Updated_Successfully'));
    return back();
}

    DB::table('whatsapp_setting')->updateOrInsert(
      [],
    [
        'url' => $request->input('url'),
        'instance_id' => $request->input('instance_id'),
        'access_token' => $request->input('access_token'),
        'message_type' => $request->input('message_type'),
        'message_otp' => $request->input('message_otp'),
        'message_new_order' => $request->input('message_new_order'),
        'message_order_confirm' => $request->input('message_confirm'),
        'message_order_cancel' => $request->input('message_cancel'),
        'message_order_processing' => $request->input('message_process'),
        'message_order_handover' => $request->input('message_handover'),
        'message_order_pickup' => $request->input('message_pickup'),
        'message_order_delivered' => $request->input('message_delivered'),
        'created_at' => now(),
        'updated_at' => now(),
    ]
);

Toastr::success(translate('messages.Whatsapp_Setting_Updated_Successfully'));
return back();


    }


}