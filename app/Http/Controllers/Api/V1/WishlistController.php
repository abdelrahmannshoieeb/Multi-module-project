<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Store;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function add_to_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
            'user_id'  => 'required_without:item_id',
        ]);

        $user_id = $request->user()->id ?? $request->user_id;

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if ($request->item_id && $request->store_id) {
            $errors = [];
            array_push($errors, ['code' => 'data', 'message' => translate('messages.can_not_add_both_food_and_restaurant_at_same_time')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $wishlist = Wishlist::where('user_id', $user_id)->where('item_id', $request->item_id)->where('store_id', $request->store_id)->first();
        if (empty($wishlist)) {
            $wishlist = new Wishlist;
            $wishlist->user_id = $user_id;
            $wishlist->item_id = $request->item_id;
            $wishlist->store_id = $request->store_id;
            $wishlist->save();
            return response()->json(['message' => translate('messages.added_successfully')], 200);
        }

        return response()->json(['message' => translate('messages.already_in_wishlist')], 409);
    }

    public function remove_from_wishlist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required_without:store_id',
            'store_id' => 'required_without:item_id',
        ]);

        $user_id = $request->user()->id ?? $request->user_id;


        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $wishlist = Wishlist::when($request->item_id, function($query)use($request){
            return $query->where('item_id', $request->item_id);
        })
        ->when($request->store_id, function($query)use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->where('user_id', $user_id)->first();

        if ($wishlist) {
            $wishlist->delete();
            return response()->json(['message' => translate('messages.successfully_removed')], 200);

        }
        return response()->json(['message' => translate('messages.not_found')], 404);
    }

    public function wish_list(Request $request)
    {
        // return dd("Hewlllo");
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => 'Zone id is required!']);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        $user_id= $request->user()->id ?? $request->user_id;
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
       $wishlist_items_ids = Wishlist::where("user_id",$user_id)->pluck("item_id")->toArray();
       $wishlist_stores_ids = Wishlist::where("user_id",$user_id)->pluck("store_id")->toArray();

       $items = Item::whereIn("id",$wishlist_items_ids)->active()->get();
       $stores = Store::whereIn("id",$wishlist_stores_ids)->active()->get();
        return response()->json([
            "items" => $items,
            "stores" => $stores,
        ], 200);
    }
    public function wish_list2(Request $request)
    {
        // return dd("Hewlllo");
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => 'Zone id is required!']);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');
        $wishlists = Wishlist::where('user_id', $request->user()->id ?? $request->user_id)->with(['item'=>function($q)use($zone_id){
            return $q->whereHas('store', function($query)use($zone_id){
                $query->when(config('module.current_module_data'), function($query){
                    $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                        $query->where('modules.id', config('module.current_module_data')['id']);
                    });
                })->whereHas('module',function($query){
                    $query->where('status',1);
                })->whereIn('zone_id', json_decode($zone_id, true));
            });
        },
         'store'=>function($q)use($zone_id,$longitude,$latitude){
            return $q->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
            })->withOpen($longitude??0,$latitude??0)->whereHas('module',function($query){
                $query->where('status',1);
            })->whereIn('zone_id', json_decode($zone_id, true));
        }])->get();
       $wishlists = Helpers::wishlist_data_formatting($wishlists, true);
        return response()->json($wishlists, 200);
    }
}
