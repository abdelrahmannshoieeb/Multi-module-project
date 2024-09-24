<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Banner;
use App\Models\Item;
use App\Models\User;
use App\Models\StoreReview;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Wishlist;
use App\Http\Requests\StoreFavouriteRequest;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;
use App\Models\Translation;
use Illuminate\Support\Facades\Storage;
use App\Models\EstateInfo;
use App\Models\Tag;

class MarketPlaceCategoriesController extends Controller
{



    public function get_pharmacy(Request $request){

        $module_id = $request->module_id ?? 0;
        $latitude = $request->latitude ?? 0;
        $longitude = $request->longitude ?? 0;

        // Top Pharmacies
        $top_pharmacy = Store::where('module_id', $module_id)
        ->where('category_type', 'pharmacy')
        ->orderBy('rating', 'desc')
        ->take(10)
        ->get();


        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        // Near Pharmacy
        $near_pharmacy = Store::where('module_id', $module_id)
            ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) *
                        cos( radians( latitude ) )
                        * cos( radians( longitude ) - radians(?)
                        ) + sin( radians(?) ) *
                        sin( radians( latitude ) ) )
                        ) AS distance", [$latitude, $longitude, $latitude])
            ->having('distance', '<', 50) // 50 km radius
            ->orderBy('distance')
            ->get();

        // Opened Pharmacy
        $open_pharmacy = Store::where('module_id', $module_id)
        ->where('always_opened', 1)
        ->get();


        $banner = Banner::where("module_id",$module_id)->get();


        return response()->json([
          "data"=>[
            'status'=> 'success',
            'banner' => $banner,
            'top_pharmacy' => $top_pharmacy,
            'near_pharmacy' => $near_pharmacy,
            'open_pharmacy' => $open_pharmacy,

          ]
        ]);
    }

    public function get_categories_with_sub(Request $request){

        $categories = Category::query()->active()->with('childes')->get();


        return response()->json([
            'status'=> 'success',
            'categories' => $categories
        ]);
    }

    public function search_supermarket(Request $request){
        $keyword = $request->keyword;
        $module_id = $request->module_id;
        $banner = Banner::query()->module($module_id)->get();
        $results = Item::query()->active()->module($module_id)->where("name","like","%$keyword%")->get();

        return response(['data' => [
            'status' => 'success',
            'banner' => $banner,
            'results' => $results,
        ]], 200);


    }
    public function get(Request $request)
    {
        $module_id = $request->module_id ?? 0;
        $categories = Category::where('module_id', $module_id)->get();
        $banner = Banner::where('module_id', $module_id)->get();
        $newTending = Item::active()->where('module_id', $module_id)->orderBy('id', 'desc')->take(10)->get();
        $similar_product = Item::active()->where('module_id', $module_id)->orderBy('id', 'asc')->inRandomOrder()->limit(10)->get();


        return response(['data' => [
            'status'=> 'success',
            'categories' => $categories,
            'banner' => $banner,
            'new_Trending' => $newTending,
            'similar_product' => $similar_product,
        ]], 200);
    }

    public function get_categories(Request $request)
    {
        $module_id = $request->module_id ?? 0;
        $categories =  Category::where('module_id', $module_id)->get();

        return response(['data' => [
            'status' => 'success',
            'categories' => $categories,
        ]], 200);
    }

    public function get_seller_profile(Request $request)
    {
        try {
            $seller_id = $request->seller_id ?? -1;

            // Find the store
            $store = Store::where('user_id', $seller_id)->first();

            // Check if store exists
            if (!$store) {
                return response([
                    'data' => [
                        'status' => 'error',
                        'message' => 'Store not found',
                    ]
                ], 404);
            }

            // Get related data
            $profile = $store->user;
            $reviews = $store->store_reviews;
            $items = $store->items;

            // Transform reviews
            $reviews_transform = $reviews->transform(function($review){
                $review['customer'] = User::where("id", $review->user_id)->first();
                return $review;
            });

            $reviewsx = StoreReview::where('store_id', $seller_id)
            // ->where('module_id', $module_id)
            ->with('users') // this will include all user details
            ->get();

            // // Transform items
            // $items = $items->transform(function ($item) {
            //     $item->add_ons = json_decode($item->add_ons, true);
            //     $item->attributes = json_decode($item->attributes, true);
            //     $item->choice_options = json_decode($item->choice_options, true);
            //     $item->variations = json_decode($item->variations, true);
            //     $item->category_ids = (array)($item->category_ids);
            //     $item->food_variations = json_decode($item->food_variations, true);
            //     return $item;
            // });

            // Get the profile with related data
            $profile = Store::where('user_id', $seller_id)->with('user')->with(['store_reviews', 'items'])->first();

            return response([
                'data' => [
                    'status' => 'success',
                    'profile' => $profile,
                    'reviews' => $reviewsx,
                    'items' => $items,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response([
                'data' => [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]
            ], 200);
        }
    }


    public function get_products(Request $request)
    {
        $category_id = $request->category_id ?? 0;
        $filter = $request->filter ?? 0;
        $type   = $request->type ?? 0;


        switch($filter)
        {
            case 'price' :
                switch($type)
                {
                    case 'budjet_friendly':
                        $items = Item::where('category_id', $category_id)->where('price' ,'<=', 300)->get();
                        break;
                    case 'mid_range' :
                        $items = Item::where('category_id', $category_id)->where('price' ,'>=', 301)->where('price', '<=', 1000)->get();
                        break;
                    case 'luxury' :
                        $items = Item::where('category_id', $category_id)->where('price' ,'>=', 1001)->get();
                        break;
                }
                break;
            case 'location' :
                break;
            case 'rating' :
                switch($type){
                    case 'lower':
                        $items = Item::where('category_id' , $category_id)->where('rating' , '<', 2)->get();
                        break;
                    case 'middle':
                        $items = Item::where('category_id' , $category_id)->where('rating' , '>=', 2)->where('rating', '<', 3)->get();
                        break;
                    case 'higher':
                        $items = Item::where('category_id' , $category_id)->where('rating' , '>=', 3)->where('rating', '<=', 5)->get();
                        break;
                }
                break;
            case 'category' :
                break;
        }

        if(!isset($items)){
            $items = Item::active()->where('category_id' , $category_id)->get();
        }

        return response([
            'date' => [
                'status' => 'success',
                'items' => $items,
            ]
        ]);

    }

    public function get_products_by_categoryId(Request $request)
    {
        $category_id = $request->category_id ?? 0;


        $items = Item::active()->where('category_id' , $category_id)->get();

        return response([
            'date' => [
                'status' => 'success',
                'items' => $items,
            ]
        ]);

    }

    public function get_favorites(Request $request, $id)
    {
        $filter = $request->filter ?? 0;
        $type   = $request->type ?? 0;

        $items = Item::active()->whereHas('whislists', function($q) use ($id) {
            $q->where('user_id', $id);
        });

        switch($filter)
        {
            case 'price' :
                switch($type)
                {
                    case 'budjet_friendly':
                        $items->where('price' ,'<=', 300);
                        break;
                    case 'mid_range' :
                        $items->where('price' ,'>=', 301)->where('price', '<=', 1000);
                        break;
                    case 'luxury' :
                        $items->where('price' ,'>=', 1001);
                        break;
                }
                break;
            case 'location' :
                break;
            case 'rating' :
                switch($type){
                    case 'lower':
                        $items->where('rating' , '<', 2);
                        break;
                    case 'middle':
                        $items->where('rating' , '>=', 2)->where('rating', '<', 3);
                        break;
                    case 'higher':
                        $items->where('rating' , '>=', 3)->where('rating', '<=', 5);
                        break;
                }
                break;
            case 'category' :
                break;
        }

        $items = $items->get();

        return response([
            'date' => [
                'status' => 'success',
                'items' => $items,
            ]
        ]);
    }

    public function store_favourite(StoreFavouriteRequest $request)
    {
        $user_id = auth()->user()->id;
        $favourite = Wishlist::where('user_id', $user_id)->where('item_id', $request->item_id)->first();

        if($favourite){
            $favourite->delete();
        }
        else{
            Wishlist::create([
                'user_id' => $user_id,
                'item_id' => $request->item_id,
            ]);
        }

        return response([
            'date' => [
                'status' => 'success',
            ]
        ]);
    }


    public function get_item(Request $request)
    {
        $item_id = $request->item_id;

        $item = Item::where('id', $item_id)->first();

        $item->add_ons = json_decode($item->add_ons, true);
        $item->attributes = json_decode($item->attributes, true);
        $item->choice_options = json_decode($item->choice_options, true);
        $item->variations = json_decode($item->variations, true);
        $item->category_ids = json_decode($item->category_ids, true);
        $item->food_variations = json_decode($item->food_variations, true);


        return response([
            'date' => [
                'status' => 'success',
                'items' => $item,
            ]
        ]);

    }


    public function sell_product(Request $request)
    {
        $user_id = $request->user_id ?? 0;

        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            'image' =>  'nullable',
            'price' => 'required|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'type' => 'required | in:product,vehicle,estate',
            'translations'=>'required',
            'seller_name' => 'required',
            'seller_email' => 'required',
            'seller_whatsapp' => 'required',
            'seller_phoneNumber' => 'required',
            'location' => 'required',
        ], [
            'category_id.required' => translate('messages.category_required'),
        ]);

        $dis = 0;
        if($request->has('discount')){
            if ($request['discount_type'] == 'percent') {
                $dis = ($request['price'] / 100) * $request['discount'];
            } else {
                $dis = $request['discount'];
            }
        }

        if ($request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', translate('messages.discount_can_not_be_more_than_or_equal'));
        }

        $data = json_decode($request->translations, true);

        if (count($data ?? []) < 1) {
            $validator->getMessageBag()->add('translations', translate('messages.Name and description in english is required'));
        }

        if ($request['price'] <= $dis || count($data) < 1 || $validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 402);
        }



        $item = new Item;
        $item->name = $data[0]['value'];

        $category = [];
        if ($request->category_id != null) {
            array_push($category, [
                'id' => $request->category_id,
                'position' => 1,
            ]);
        }
        if ($request->sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_category_id,
                'position' => 2,
            ]);
        }
        if ($request->sub_sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ]);
        }
        $item->category_id = $request->sub_category_id?$request->sub_category_id:$request->category_id;
        $item->category_ids = json_encode($category);
        $item->description = $data[1]['value'];

        $choice_options = [];
        $no=0;

        if($request->has('options') && $request->options){
            foreach (json_decode($request->options) as $key) {
                $no++;
                $i['name'] = 'choice_' . $no;
                $i['title'] = $key->title;
                $i['options'] = explode(',', $key->options);

                array_push($choice_options, $i);
            }
        }

        $item->choice_options = json_encode($choice_options);

        //test
        $variants = [];
        if($request->has('variations') && $request->variations){
            foreach (json_decode($request->variations) as $key) {
                $j['type'] = $key->type;
                $j['price'] = $key->price;
                $j['stock'] = $key->stock;

                array_push($variants, $j);
            }
        }

        $item->variations = json_encode($variants);
        $variations = [];
        $images = [];

        if($request->item_id && $request?->product_gellary == 1 ){
            $item_data= Item::withoutGlobalScope(StoreScope::class)->select(['image','images'])->findOrfail($request->item_id);

            if(!$request->has('image')){
                $oldPath = storage_path("app/public/product/{$item_data->image}");
                $newFileName =\Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png" ;
                $newPath = storage_path("app/public/product/{$newFileName}");
                if (File::exists($oldPath)) {
                    File::copy($oldPath, $newPath);
                }
            }

            $uniqueValues = array_diff($item_data->images, explode(",", $request->removedImageKeys));

            foreach($uniqueValues as$key=> $value){
                $oldPath = storage_path("app/public/product/{$value}");
                $newFileName =\Carbon\Carbon::now()->toDateString() . "-" . uniqid() . ".png" ;
                $newPath = storage_path("app/public/product/{$newFileName}");
                if (File::exists($oldPath)) {
                    File::copy($oldPath, $newPath);
                }
                $images[]=$newFileName;
            }
        }


        if (!empty($request->file('item_images'))) {
            foreach ($request->item_images as $img) {
                $image_name = Helpers::upload('product/', 'png', $img);
                $images[]=$image_name;
            }

        }

        $food_variations = [];

        if($request->type == 'vehicle')
        {
            $item->color = $request->color;
        }

        //combinations end
        $item->food_variations = json_encode($food_variations);
        $item->price = $request->price;
        $item->image =  $request->has('image') ? Helpers::upload('product/', 'png', $request->file('image')) : $newFileName ?? null;
        $item->available_time_starts = $request->available_time_starts?? null;
        $item->available_time_ends = $request->available_time_ends ?? null;
        $item->discount = ($request->discount_type == 'amount' ? $request->discount : $request->discount) ?? 0;
        $item->discount_type = $request->discount_type?? 'amount';
        $item->maximum_cart_quantity = $request->maximum_cart_quantity ?? null;
        $item->attributes = $request->has('attribute_id') ? $request->attribute_id : json_encode([]);
        $item->add_ons = $request->has('addon_ids') ? json_encode(explode(',',$request->addon_ids)) : json_encode([]);
        $item->store_id = $request['store_id'];
        $item->veg = $request->veg ?? 0;
        $item->module_id = Store::where('id', $request['store_id'])->first()['module_id'] ?? 2;
        $item->stock= $request->current_stock;
        $item->images = $images;
        $item->unit_id = $request->unit;
        $item->organic = $request->organic??0;
        $item->type = $request->type;

        $item->seller_name = $request->seller_name;
        $item->seller_email = $request->seller_email;
        $item->seller_whatsapp = $request->seller_whatsapp;
        $item->seller_phoneNumber = $request->seller_phoneNumber;
        $item->location = $request->location;

        $item->option = $request->option ?? '';
        $item->conditions = $request->condition ?? '';


        $item->save();
        $item->tags()->sync($request->tags);


        foreach ($data as $key=>$i) {
            $data[$key]['translationable_type'] = 'App\Models\Item';
            $data[$key]['translationable_id'] = $item->id;
        }
        Translation::insert($data);

        $product_approval_datas = \App\Models\BusinessSetting::where('key', 'product_approval_datas')->first()?->value ?? '';
        $product_approval_datas =json_decode($product_approval_datas , true);


        if($request->type == 'estate')
        {
            EstateInfo::create([
                'item_id' => $item['id'],
                'area' => $request->area,
                'city' => $request->city,
                'rooms_number' => $request->rooms_number,
                'bathrooms_number' => $request->bathrooms_number,
            ]);
        }



        return response()->json(['message'=>translate('messages.product_added_successfully')], 200);
    }

    public function get_recently_viewed_products()
    {
        $items = Item::inRandomOrder()->active()->limit(10)->get();
        return response([
            'date' => [
                'status' => 'success',

                'items' => $items,
            ]
        ]);
    }



    public function get_last_viewed_stores()
    {
        $stores = Store::inRandomOrder()->limit(10)->get();

        return response([
            'date' => [
                'status' => 'success',
                'stores' => $stores,
            ]
            ]);
    }

    public function get_favourite_stores($id)
    {
        $stores = Store::whereHas('wishlist', function($q) use ($id){
            $q->where('user_id', $id);
        })->get();

        return response([
            'date' => [
                'status' => 'success',
                'stores' => $stores,
            ]
        ]);


    }



}
