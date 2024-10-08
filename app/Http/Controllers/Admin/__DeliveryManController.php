<?php

namespace App\Http\Controllers\Admin;

// new added
use App\Exports\DisbursementHistoryExport;
use App\Models\DisbursementDetails;
// new added end

use App\Models\Zone;
use App\Models\Message;
use App\Models\DMReview;
use App\Models\UserInfo;
use App\Models\DeliveryMan;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Exports\DeliveryManEarningExport;
use App\Exports\DeliveryManListExport;
use App\Exports\DeliveryManReviewExport;
use App\Exports\SingleDeliveryManReviewExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\OrderTransaction;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class DeliveryManController extends Controller
{
    public function index()
    {
        return view('admin-views.delivery-man.index');
    }

    public function list(Request $request)
    {
        $key = explode(' ', $request['search']);
        $zone_id = $request->query('zone_id', 'all');
        $delivery_men = DeliveryMan::
        when(isset($key), function($query) use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('identity_number', 'like', "%{$value}%");
                }
            });
        })
        ->when(is_numeric($zone_id), function($query) use($zone_id){
            return $query->where('zone_id', $zone_id);
        })
        ->with('zone')->where('type','zone_wise')
        ->where('application_status', 'approved')
        ->latest()->paginate(config('default_pagination'));
        $zone = is_numeric($zone_id)?Zone::findOrFail($zone_id):null;
        return view('admin-views.delivery-man.list', compact('delivery_men', 'zone'));
    }

    public function new_delivery_man(Request $request)
    {
        $search_by = $request->query('search_by');
        $key = explode(' ', $search_by);
        $zone_id = $request->query('zone_id', 'all');
        $delivery_men = DeliveryMan::when(is_numeric($zone_id), function($query) use($zone_id){
            return $query->where('zone_id', $zone_id);
        })->with('zone')->where('type','zone_wise')
        ->where('application_status', 'pending')
        ->when($search_by, function($query)use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('identity_number', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate(config('default_pagination'));
        $zone = is_numeric($zone_id)?Zone::findOrFail($zone_id):null;
        return view('admin-views.delivery-man.new', compact('delivery_men', 'zone', 'search_by'));
    }

    public function deny_delivery_man(Request $request)
    {
        $search_by = $request->query('search_by');
        $key = explode(' ', $search_by);
        $zone_id = $request->query('zone_id', 'all');
        $delivery_men = DeliveryMan::when(is_numeric($zone_id), function($query) use($zone_id){
            return $query->where('zone_id', $zone_id);
        })->with('zone')->where('type','zone_wise')
        ->where('application_status', 'denied')
        ->when($search_by, function($query)use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('identity_number', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate(config('default_pagination'));
        $zone = is_numeric($zone_id)?Zone::findOrFail($zone_id):null;
        return view('admin-views.delivery-man.deny', compact('delivery_men', 'zone', 'search_by'));
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $delivery_men=DeliveryMan::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->where('type','zone_wise')
        ->where('application_status', 'approved')
        ->get();
        return response()->json([
            'view'=>view('admin-views.delivery-man.partials._table',compact('delivery_men'))->render(),
            'count'=>$delivery_men->count()
        ]);
    }

    public function active_search(Request $request){
        $key = explode(' ', $request['search']);
        $delivery_men=DeliveryMan::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->where('type','zone_wise')
        ->Active()
        ->first();
        return response()->json([
            'dm'=>$delivery_men
        ]);
    }

    public function reviews_list(Request $request){
        $key = explode(' ', $request['search']);
        $reviews=DMReview::with(['delivery_man','customer'])->whereHas('delivery_man',function($query) use ($key){
            foreach ($key as $value) {
                $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%");
            }
        })->latest()->paginate(config('default_pagination'));
        return view('admin-views.delivery-man.reviews-list',compact('reviews'));
    }

    public function reviews_export(Request $request){
        $key = explode(' ', $request['search']);
        $reviews=DMReview::with(['delivery_man','customer'])->whereHas('delivery_man',function($query) use ($key){
            foreach ($key as $value) {
                $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%");
            }
        })->get();

        $data = [
            'reviews'=>$reviews,
            'search'=>$request->search??null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DeliveryManReviewExport($data), 'DeliveryManReviews.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DeliveryManReviewExport($data), 'DeliveryManReviews.csv');
        }
    }

    public function preview(Request $request, $id, $tab='info')
    {

        // new added
        $key = explode(' ', $request['search']);
        // new added end
        $dm = DeliveryMan::with(['reviews'])->where('type','zone_wise')->where(['id' => $id])->first();
        if($tab == 'info')
        {
            $reviews=DMReview::where(['delivery_man_id'=>$id])->latest()->paginate(config('default_pagination'));
            return view('admin-views.delivery-man.view.info', compact('dm', 'reviews'));
        }
        else if($tab == 'transaction')
        {
            $date = $request->query('date');
            return view('admin-views.delivery-man.view.transaction', compact('dm', 'date'));
        }
        else if($tab == 'conversation')
        {
            $user = UserInfo::where(['deliveryman_id' => $id])->first();
            if($user){
                $conversations = Conversation::with(['sender', 'receiver', 'last_message'])->WhereUser($user->id)->paginate(8);
            }else{
                $conversations = [];
            }

            return view('admin-views.delivery-man.view.conversations', compact('conversations','dm'));
        }

        // new added
        else if ($tab == 'disbursement') {
            $disbursements=DisbursementDetails::where('delivery_man_id', $dm->id)
                ->when(isset($key), function ($q) use ($key){
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('disbursement_id', 'like', "%{$value}%")
                                ->orWhere('status', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(config('default_pagination'));
            return view('admin-views.delivery-man.view.disbursement', compact('dm','disbursements'));

        }

        // new added end
    }

    public function review_export(Request $request){
        $dm = DeliveryMan::with(['reviews'])->where('type','zone_wise')->where(['id' => $request->id])->first();
        $reviews=DMReview::where(['delivery_man_id'=>$request->id])->latest()->get();

        $data = [
            'dm'=>$dm,
            'reviews'=>$reviews,
            'search'=>$request->search??null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new SingleDeliveryManReviewExport($data), 'DeliveryManReviews.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new SingleDeliveryManReviewExport($data), 'DeliveryManReviews.csv');
        }
    }

// new added
    public function disbursement_export(Request $request,$id,$type)
    {
        $key = explode(' ', $request['search']);

        $dm= DeliveryMan::find($id);
        $disbursements=DisbursementDetails::where('delivery_man_id', $dm->id)
            ->when(isset($key), function ($q) use ($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('disbursement_id', 'like', "%{$value}%")
                            ->orWhere('status', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->get();
        $data = [
            'disbursements'=>$disbursements,
            'search'=>$request->search??null,
            'delivery_man'=>$dm->f_name.' '.$dm->l_name,
            'type'=>'dm',
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.csv');
        }
    }

// new added end
    public function earning_export(Request $request){
        $date = $request->date;
        $dm = DeliveryMan::with(['reviews'])->where('type','zone_wise')->where(['id' => $request->id])->first();
        $earnings=OrderTransaction::where('delivery_man_id', $request->id)
        ->when($date, function($query)use($date){
            return $query->whereDate('created_at', $date);
        })->get();

        $data = [
            'dm'=>$dm,
            'earnings'=>$earnings,
            'date'=>$request->date??null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.csv');
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => 'required|unique:delivery_men',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20|unique:delivery_men',
            'zone_id' => 'required',
            'earning' => 'required',
            'vehicle_id' => 'required',
            'password' => ['required'],
        ], [
            'f_name.required' => translate('messages.first_name_is_required'),
            'zone_id.required' => translate('messages.select_a_zone'),
            'vehicle_id.required' => translate('messages.select_a_vehicle'),
            'earning.required' => translate('messages.select_dm_type')
        ]);

        if ($request->has('image')) {
            $image_name = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($id_img_names, $identity_image);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        $dm = New DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->identity_number = $request->identity_number;
        $dm->identity_type = $request->identity_type;
        $dm->vehicle_id = $request->vehicle_id;
        $dm->zone_id = $request->zone_id;
        $dm->identity_image = $identity_image;
        $dm->image = $image_name;
        $dm->active = 0;
        $dm->earning = $request->earning;
        $dm->password = bcrypt($request->password);
        $dm->save();

        Toastr::success(translate('messages.deliveryman_added_successfully'));
        return redirect('admin/users/delivery-man/list');
    }

    public function edit($id)
    {
        $delivery_man = DeliveryMan::find($id);
        return view('admin-views.delivery-man.edit', compact('delivery_man'));
    }

    public function status(Request $request)
    {
        $delivery_man = DeliveryMan::find($request->id);
        $delivery_man->status = $request->status;

        try
        {
            if($request->status == 0)
            {   $delivery_man->auth_token = null;
                if(isset($delivery_man->fcm_token))
                {
                    $data = [
                        'title' => translate('messages.suspended'),
                        'description' => translate('messages.your_account_has_been_suspended'),
                        'order_id' => '',
                        'image' => '',
                        'type'=> 'block'
                    ];
                    Helpers::send_push_notif_to_device($delivery_man->fcm_token, $data);

                    DB::table('user_notifications')->insert([
                        'data'=> json_encode($data),
                        'delivery_man_id'=>$delivery_man->id,
                        'created_at'=>now(),
                        'updated_at'=>now()
                    ]);
                }

                $mail_status = Helpers::get_mail_status('suspend_mail_status_dm');
                if (config('mail.status') && $mail_status == '1') {
                    Mail::to($delivery_man['email'])->send(new \App\Mail\DmSuspendMail($delivery_man['f_name']));
                }

            }

        }
        catch (\Exception $e) {
            Toastr::warning(translate('messages.push_notification_faild'));
        }

        $delivery_man->save();

        Toastr::success(translate('messages.deliveryman_status_updated'));
        return back();
    }

    public function reviews_status(Request $request)
    {
        $review = DMReview::find($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.review_visibility_updated'));
        return back();
    }

    public function earning(Request $request)
    {
        $delivery_man = DeliveryMan::find($request->id);
        $delivery_man->earning = $request->status;

        $delivery_man->save();

        Toastr::success(translate('messages.deliveryman_type_updated'));
        return back();
    }

    public function update_application(Request $request)
    {
        $delivery_man = DeliveryMan::findOrFail($request->id);
        $delivery_man->application_status = $request->status;
        if($request->status == 'approved') $delivery_man->status = 1;
        $delivery_man->save();
        try{
            if($request->status=='approved'){

                $mail_status = Helpers::get_mail_status('approve_mail_status_dm');
                if(config('mail.status') && $mail_status == '1'){
                    Mail::to($delivery_man->email)->send(new \App\Mail\DmSelfRegistration('approved',$delivery_man->f_name.' '.$delivery_man->l_name));
                }
            }else{

                $mail_status = Helpers::get_mail_status('deny_mail_status_dm');
                if(config('mail.status') && $mail_status == '1'){
                    Mail::to($delivery_man->email)->send(new \App\Mail\DmSelfRegistration('denied', $delivery_man->f_name.' '.$delivery_man->l_name));
                }
            }
        }catch(\Exception $ex){
            info($ex->getMessage());
        }
        Toastr::success(translate('messages.application_status_updated_successfully'));
        return back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => 'required|unique:delivery_men,email,'.$id,
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:delivery_men,phone,'.$id,
            'vehicle_id' => 'required',
            'earning' => 'required',
            'password' => ['nullable'],
        ], [
            'f_name.required' => translate('messages.first_name_is_required'),
            'vehicle_id.required' => translate('messages.select_a_vehicle'),
            'earning.required' => translate('messages.select_dm_type')
        ]);

        $delivery_man = DeliveryMan::find($id);

        if ($request->has('image')) {
            $image_name = Helpers::update('delivery-man/', $delivery_man->image, 'png', $request->file('image'));
        } else {
            $image_name = $delivery_man['image'];
        }

        if ($request->has('identity_image')){
            foreach (json_decode($delivery_man['identity_image'], true) as $img) {
                if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                    Storage::disk('public')->delete('delivery-man/' . $img);
                }
            }
            $img_keeper = [];
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($img_keeper, $identity_image);
            }
            $identity_image = json_encode($img_keeper);
        } else {
            $identity_image = $delivery_man['identity_image'];
        }

        $delivery_man->f_name = $request->f_name;
        $delivery_man->l_name = $request->l_name;
        $delivery_man->email = $request->email;
        $delivery_man->phone = $request->phone;
        $delivery_man->identity_number = $request->identity_number;
        $delivery_man->vehicle_id = $request->vehicle_id;
        $delivery_man->identity_type = $request->identity_type;
        $delivery_man->zone_id = $request->zone_id;
        $delivery_man->identity_image = $identity_image;
        $delivery_man->image = $image_name;
        $delivery_man->earning = $request->earning;
        $delivery_man->password = strlen($request->password)>1?bcrypt($request->password):$delivery_man['password'];
        $delivery_man->save();
        if($delivery_man->userinfo) {
            $userinfo = $delivery_man->userinfo;
            $userinfo->f_name = $request->f_name;
            $userinfo->l_name = $request->l_name;
            $userinfo->email = $request->email;
            $userinfo->image = $image_name;
            $userinfo->save();
        }
        Toastr::success(translate('messages.deliveryman_updated_successfully'));
        return redirect('admin/users/delivery-man/list');
    }

    public function delete(Request $request)
    {
        $delivery_man = DeliveryMan::find($request->id);
        if (Storage::disk('public')->exists('delivery-man/' . $delivery_man['image'])) {
            Storage::disk('public')->delete('delivery-man/' . $delivery_man['image']);
        }

        foreach (json_decode($delivery_man['identity_image'], true) as $img) {
            if (Storage::disk('public')->exists('delivery-man/' . $img)) {
                Storage::disk('public')->delete('delivery-man/' . $img);
            }
        }

        if($delivery_man->userinfo){

            $delivery_man->userinfo->delete();
        }

        $delivery_man->delete();
        Toastr::success(translate('messages.deliveryman_deleted_successfully'));
        return back();
    }

    public function get_deliverymen(Request $request){
        $key = explode(' ', $request->q);
        $zone_ids = isset($request->zone_ids)?(count($request->zone_ids)>0?$request->zone_ids:[]):0;
        $data=DeliveryMan::when($zone_ids, function($query) use($zone_ids){
            return $query->whereIn('zone_id', $zone_ids);
        })
        ->when($request->earning, function($query){
            return $query->earning();
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->active()->limit(8)->get(['id',DB::raw('CONCAT(f_name, " ", l_name) as text')]);
        return response()->json($data);
    }

    public function get_account_data(DeliveryMan $deliveryman)
    {
        $wallet = $deliveryman->wallet;
        $cash_in_hand = 0;
        $balance = 0;

        if($wallet)
        {
            $cash_in_hand = $wallet->collected_cash;
            $balance = round($wallet->total_earning - $wallet->total_withdrawn - $wallet->pending_withdraw, config('round_up_to_digit'));
        }
        return response()->json(['cash_in_hand'=>$cash_in_hand, 'earning_balance'=>$balance], 200);

    }

    public function get_conversation_list(Request $request)
    {
        $user = UserInfo::where(['deliveryman_id' => $request->user_id])->first();
        $dm = DeliveryMan::find($request->user_id);
        if($user){
            $conversations = Conversation::with(['sender', 'receiver', 'last_message'])->WhereUser($user->id);
            if($request->query('key')) {
                $key = explode(' ', $request->get('key'));
                $conversations = $conversations->where(function($qu)use($key){
                    $qu->where(function($q)use($key){
                        $q->where('sender_type','!=', 'delivery_man')->whereHas('sender',function($query)use($key){
                            foreach ($key as $value) {
                                $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                            }
                        });
                    })->orWhere(function($q)use($key){
                        $q->where('receiver_type','!=', 'delivery_man')->whereHas('receiver',function($query)use($key){
                            foreach ($key as $value) {
                                $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                            }
                        });
                    });
                });
            }
            $conversations = $conversations->WhereUserType('delivery_man')->paginate(8);
        }else{
            $conversations = [];
        }

        $view = view('admin-views.delivery-man.partials._conversation_list',compact('conversations','dm'))->render();
        return response()->json(['html'=>$view]);

    }

    public function conversation_view($conversation_id,$user_id)
    {
        $convs = Message::where(['conversation_id' => $conversation_id])->get();
        $conversation = Conversation::find($conversation_id);
        $receiver = UserInfo::find($conversation->receiver_id);
        $sender = UserInfo::find($conversation->sender_id);
        $user = UserInfo::find($user_id);
        return response()->json([
            'view' => view('admin-views.delivery-man.partials._conversations', compact('convs', 'user', 'receiver'))->render()
        ]);
    }

    public function review_search(Request $request)
    {
        $key = explode(' ', $request['search']);
        $reviews=DMReview::with(['delivery_man','customer'])->whereHas('delivery_man',function($query) use ($key){
            foreach ($key as $value) {
                $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%");
            }
        })->get();

        return response()->json([
            'view' => view('admin-views.delivery-man.partials._review', compact('reviews'))->render(),
            'count' => $reviews->count()
        ]);
    }

    public function export(Request $request){
        $key = explode(' ', $request['search']);
        $zone_id = $request->query('zone_id', 'all');
        $delivery_men = DeliveryMan::when(isset($key), function($query) use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%")
                        ->orWhere('identity_number', 'like', "%{$value}%");
                }
            });
        })->when(is_numeric($zone_id), function($query) use($zone_id){
            return $query->where('zone_id', $zone_id);
        })->with('zone')->where('type','zone_wise')
        ->where('application_status', 'approved')
        ->get();

        $data = [
            'delivery_men'=>$delivery_men,
            'search'=>$request->search??null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DeliveryManListExport($data), 'DeliveryMans.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DeliveryManListExport($data), 'DeliveryMans.csv');
        }
    }
}
