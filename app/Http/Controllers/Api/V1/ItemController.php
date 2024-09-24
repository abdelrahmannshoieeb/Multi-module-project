<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Order;
use App\Models\Review;
use App\Models\Category;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\CategoryLogic;
use Illuminate\Support\Facades\DB;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Tag;
use App\Scopes\StoreScope;
use App\Models\TempProduct;
use App\Models\Translation;
use Illuminate\Validation\Rule;
use App\Models\PharmacyItemDetails;
use Illuminate\Support\Facades\File;


class ItemController extends Controller
{

    public function get_latest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'category_id' => 'required',
            'limit' => 'nullable',
            'offset' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');

        $items = ProductLogic::get_latest_products($zone_id,  $request['store_id'], $request['category_id'], $type,$min,$max,$product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_new_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = isset($request['limit'])?$request['limit']:50;
        $offset = isset($request['offset'])?$request['offset']:1;

        $items = ProductLogic::get_new_products($zone_id, $type,$min,$max,$product_id,$limit,$offset);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_searched_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;
        $category_ids = $request['category_ids']?(is_array($request['category_ids'])?$request['category_ids']:json_decode($request['category_ids'])):'';
        $filter = $request['filter']?(is_array($request['filter'])?$request['filter']:str_getcsv(trim($request['filter'], "[]"), ',')):'';
        $type = $request->query('type', 'all');
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $rating_count = $request->query('rating_count');

        $items = Item::active()->type($type)
        ->with('store', function($query){
            $query->withCount(['campaigns'=> function($query){
                $query->Running();
            }]);
        })
        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($category_ids, function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category.parent',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });

        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->orderByRaw("FIELD(name, ?) DESC", [$request['name']])
        ->when($filter&&in_array('top_rated',$filter),function ($qurey){
            $qurey->withCount('reviews')->orderBy('reviews_count','desc');
        })
        ->when($filter&&in_array('popular',$filter),function ($qurey){
            $qurey->popular();
        })
        ->when($filter&&in_array('discounted',$filter),function ($qurey){
            $qurey->Discounted()->orderBy('discount','desc');
        })
        ->when($filter&&in_array('high',$filter),function ($qurey){
            $qurey->orderBy('price', 'desc');
        })
        ->when($filter&&in_array('low',$filter),function ($qurey){
            $qurey->orderBy('price', 'asc');
        })
        ;


        $item_categories = Item::active()->type($type)
        ->with('store', function($query){
            $query->withCount(['campaigns'=> function($query){
                $query->Running();
            }]);
        })
        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($category_ids, function($query)use($category_ids){
            $query->whereHas('category',function($q)use($category_ids){
                return $q->whereIn('id',$category_ids)->orWhereIn('parent_id', $category_ids);
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category.parent',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });

        })
        ->when($rating_count, function($query) use ($rating_count){
            $query->where('avg_rating', '>=' , $rating_count);
        })
        ->when($min && $max, function($query)use($min,$max){
            $query->whereBetween('price',[$min,$max]);
        })
        ->pluck('category_id')->toArray();

        $item_categories = array_unique($item_categories);

        $categories = Category::withCount(['products','childes'])->with(['childes' => function($query)  {
            $query->withCount(['products','childes']);
        }])
        ->where(['position'=>0,'status'=>1])
        ->when(config('module.current_module_data'), function($query){
            $query->module(config('module.current_module_data')['id']);
        })
        ->whereIn('id',$item_categories)
        ->orderBy('priority','desc')->get();

        $data =  [
            'total_size' => $items->total(),


            'products' => $items->items(),
            'categories'=>$categories
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_searched_products_suggestion(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone_id = $request->header('zoneId');

        $key = explode(' ', $request['name']);

        $limit = $request['limit']??10;
        $offset = $request['offset']??1;

        $type = $request->query('type', 'all');

        $items = Item::active()->type($type)

        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
        })->select(['name','image'])

        ;

        $data =  [
            'total_size' => $items->total(),


            'products' => $items->items()
        ];

        return response()->json($data, 200);
    }

    public function get_popular_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::popular_products($zone_id,  $type);
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::most_reviewed_products($zone_id,  $type);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_discounted_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $category_ids = $request->query('category_ids', '');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::discounted_products($zone_id,  $type, $category_ids);
        //$items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_cart_suggest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');

        $type = $request->query('type', 'all');
        $recommended = $request->query('recommended');

        $items = ProductLogic::cart_suggest_products($zone_id, $request['store_id'],  $type,$recommended);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_product($id)
    {
        try {
            $item = Item::withCount('whislists')->with(['tags','reviews','reviews.customer'])->active()
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($id),function ($qurey) use($id){
                $qurey-> where('id', $id);
            })
            ->when(!is_numeric($id),function ($qurey) use($id){
                $qurey-> where('slug', $id);
            })
            ->first();
            $store = StoreLogic::get_store_details($item->store_id);
            if($store)
            {
                $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('categories.position as positions, IF((categories.position = "0"), categories.id, categories.parent_id) as categories')
                ->where('items.store_id', $item->store_id)
                ->where('categories.status',1)
                ->groupBy('categories','positions')
                ->get();

                $store = Helpers::store_data_formatting($store);
                $store['category_ids'] = array_map('intval', $category_ids->pluck('categories')->toArray());
                $store['category_details'] = Category::whereIn('id',$store['category_ids'])->get();
                $store['price_range']  = Item::withoutGlobalScopes()->where('store_id', $item->store_id)
                ->select(DB::raw('MIN(price) AS min_price, MAX(price) AS max_price'))
                ->get(['min_price','max_price']);
            }
            $item = Helpers::product_data_formatting($item, false, false, app()->getLocale());
            $item['store_details'] = $store;
            return response()->json($item, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
            ], 404);
        }
    }

    public function get_related_products(Request $request,$id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_products($zone_id,$id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }
    public function get_related_store_products(Request $request,$id)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        if (Item::find($id)) {
            $items = ProductLogic::get_related_store_products($zone_id,$id);
            $items = Helpers::product_data_formatting($items, true, false, app()->getLocale());
            return response()->json($items, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }

    public function get_recommended(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $filter = $request->query('filter', 'all');

        $zone_id= $request->header('zoneId');
        $items = ProductLogic::recommended_items($zone_id, $request->store_id, $type, $filter);
        $items['items'] = Helpers::product_data_formatting($items['items'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_set_menus()
    {
        try {
            $items = Helpers::product_data_formatting(Item::active()->with(['rating'])->where(['set_menu' => 1, 'status' => 1])->get(), true, false, app()->getLocale());
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!']
            ], 404);
        }
    }

    public function get_product_reviews(Request $request, $item_id)
    {
        if(isset($request['limit']) && ($request['limit'] != null) && isset($request['offset']) && ($request['offset'] != null)){

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->paginate($request['limit'], ['*'], 'page', $request['offset']);
            $total = $reviews->total();
        }else{

            $reviews = Review::with(['customer', 'item'])->where(['item_id' => $item_id])->active()->get();
            $total = $reviews->count();
        }

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            if($temp->item)
            {
                $temp['item_name'] = $temp->item->name;
                if(count($temp->item->translations)>0)
                {
                    $translate = array_column($temp->item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
            }

            unset($temp['item']);
            array_push($storage, $temp);
        }

        $data =  [
            'total_size' => $total,


            'reviews' => $storage
        ];

        return response()->json($data, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $item = Item::find($id);
            $overallRating = ProductLogic::get_overall_rating($item->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required',
            'order_id' => 'required',
            'comment' => 'required',
            'rating' => 'required|numeric|max:5',
        ]);

        $order = Order::find($request->order_id);
        if (isset($order) == false) {
            $validator->errors()->add('order_id', translate('messages.order_data_not_found'));
        }

        $item = Item::find($request->item_id);
        if (isset($order) == false) {
            $validator->errors()->add('item_id', translate('messages.item_not_found'));
        }

        $multi_review = Review::where(['item_id' => $request->item_id, 'user_id' => $request->user()->id, 'order_id'=>$request->order_id])->first();
        if (isset($multi_review)) {
            return response()->json([
                'errors' => [
                    ['code'=>'review','message'=> translate('messages.already_submitted')]
                ]
            ], 403);
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $order?->OrderReference?->update([
            'is_reviewed' => 1
        ]);

        $review->user_id = $request->user()->id;
        $review->item_id = $request->item_id;
        $review->order_id = $request->order_id;
        $review->module_id = $order->module_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        if($item->store)
        {
            $store_rating = StoreLogic::update_store_rating($item->store->rating, (int)$request->rating);
            $item->store->rating = $store_rating;
            $item->store->save();
        }

        $item->rating = ProductLogic::update_rating($item->rating, (int)$request->rating);
        $item->avg_rating = ProductLogic::get_avg_rating(json_decode($item->rating, true));
        $item->save();
        $item->increment('rating_count');

        return response()->json(['message' => translate('messages.review_submited_successfully')], 200);
    }

    public function item_or_store_search(Request $request){

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        if (!$request->hasHeader('longitude') || !$request->hasHeader('latitude')) {
            $errors = [];
            array_push($errors, ['code' => 'longitude-latitude', 'message' => translate('messages.longitude-latitude_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
        $zone_id= $request->header('zoneId');
        $longitude= $request->header('longitude');
        $latitude= $request->header('latitude');

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $key = explode(' ', $request->name);

        $items = Item::active()->whereHas('store', function($query)use($zone_id){
            $query->when(config('module.current_module_data'), function($query){
                $query->where('module_id', config('module.current_module_data')['id'])->whereHas('zone.modules',function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            })->whereIn('zone_id', json_decode($zone_id, true));
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orwhere('name', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%");
            }
            $q->orWhereHas('translations',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('value', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category.parent',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
            $q->orWhereHas('category',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    };
                });
            });
        })
        ->limit(50)
        ->get(['id','name','image']);

        $stores = Store::whereHas('zone.modules', function($query){
            $query->where('modules.id', config('module.current_module_data')['id']);
        })->withOpen($longitude??0,$latitude??0)->with(['discount'=>function($q){
            return $q->validate();
        }])->weekday()->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })
        ->when(config('module.current_module_data'), function($query)use($zone_id){
            $query->module(config('module.current_module_data')['id']);
            if(!config('module.current_module_data')['all_zone_service']) {
                $query->whereIn('zone_id', json_decode($zone_id, true));
            }
        })
        ->active()
        ->limit(50)
        ->select(['id','name','logo'])
        ->get();

        return [
            'items' => $items,
            'stores' => $stores
        ];

    }

    public function get_store_condition_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'store_id' => 'required',
            'limit' => 'nullable',
            'offset' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone_id= $request->header('zoneId');

        $type = $request->query('type', 'all');
        $limit = $request['limit'];
        $offset = $request['offset'];

        $paginator = Item::
        whereHas('module.zones', function($query)use($zone_id){
            $query->whereIn('zones.id', json_decode($zone_id, true));
        })
        ->whereHas('store', function($query)use($zone_id){
            $query->whereIn('zone_id', json_decode($zone_id, true))->whereHas('zone.modules',function($query){
                $query->when(config('module.current_module_data'), function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                });
            });
        })
        ->whereHas('pharmacy_item_details',function($q){
            return $q->whereNotNull('common_condition_id');
        })
        ->when(is_numeric($request->store_id),function ($qurey) use($request){
            $qurey->where('store_id', $request->store_id);
        })
        ->when(!is_numeric($request->store_id), function ($query) use ($request) {
            $query->whereHas('store', function ($q) use ($request) {
                $q->where('slug', $request->store_id);
            });
        })
        ->active()->type($type)->latest();
        $data=[



            'products' => $paginator->get()//items()
        ];
        $data['products'] = Helpers::product_data_formatting($data['products'] , true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function get_popular_basic_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable',
            'offset' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $product_id = $request->query('product_id')??null;
        $min = $request->query('min_price');
        $max = $request->query('max_price');
        $limit = $request['limit']??25;
        $offset = $request['offset']??1;

        $items = ProductLogic::get_popular_basic_products($zone_id, $limit, $offset, $type, $request['store_id'], $request['category_id'], $min,$max,$product_id);
        $items['categories'] = $items['categories'];
        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }

    public function get_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]];
            return response()->json(['errors' => $errors], 403);
        }

        $data_type = $request->query('data_type', 'all');

        $zone_id = $request->header('zoneId');
        $type = $request->query('type', 'all');
        $filter = $request->query('filter', '');
        $filter = $filter?(is_array($filter)?$filter:str_getcsv(trim($filter, "[]"), ',')):'';
        $category_ids = $request->query('category_ids', '');

        // Common parameters for all product types
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);
        $min_price = $request->query('min_price');
        $max_price = $request->query('max_price');
        $rating_count = $request->query('rating_count');
        $product_id = $request->query('product_id');

        switch ($data_type) {
            case 'searched':
                return $this->get_searched_products($request);
                break;
            case 'discounted':
                $items = ProductLogic::discounted_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count);
                break;
            case 'new':
                $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count);
                break;
            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count);
                break;
            default:
            $items =  [
                'total_size' => 0,


                'products' => [],
                'categories' => [],
            ];
        }

        $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
        return response()->json($items, 200);
    }



    // marketplace sell products



    public function store(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'category_id' => 'required',
            'image' =>  'required',
            'price' => 'required|numeric|min:0.01',
            'discount' => 'required|numeric|min:0',
            'translations'=>'required',
        ], [
            'category_id.required' => translate('messages.category_required'),
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', translate('messages.discount_can_not_be_more_than_or_equal'));
        }

        $data = json_decode($request->translations, true);

        if (count($data) < 1) {
            $validator->getMessageBag()->add('translations', translate('messages.Name and description in english is required'));
        }

        if ($request['price'] <= $dis || count($data) < 1 || $validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 402);
        }

        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(",", $request->tags);
        }
        if(isset($tags)){
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                array_push($tag_ids,$tag->id);
            }
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
        if ($request->has('choice')) {
            foreach (json_decode($request->choice_no) as $key => $no) {
                $str = 'choice_options_' . $no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', translate('messages.attribute_choice_option_value_can_not_be_null'));
                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $i['name'] = 'choice_' . $no;
                $i['title'] = json_decode($request->choice)[$key];
                $i['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', json_decode($request[$str]))));
                array_push($choice_options, $i);
            }
        }
        $item->choice_options = json_encode($choice_options);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach (json_decode($request->choice_no) as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', json_decode($request[$name]));
                array_push($options, explode(',', $my_str));
            }
        }
        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $i) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $i);
                    } else {
                        $str .= str_replace(' ', '', $i);
                    }
                }
                $i = [];
                $i['type'] = $str;
                $i['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $i['stock'] = abs($request['stock_' . str_replace('.', '_', $str)]);
                array_push($variations, $i);
            }
        }


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

        //combinations end
        $item->variations = json_encode($variations);

        $food_variations = [];
        if(isset($request->options))
        {
            foreach(json_decode($request->options, true) as $option)
            {
                $temp_variation['name']= $option['name'];
                $temp_variation['type']= $option['type'];
                $temp_variation['min']= $option['min'] ?? 0;
                $temp_variation['max']= $option['max'] ?? 0;
                $temp_variation['required']= $option['required']??'off';
                $temp_value = [];
                foreach($option['values'] as $value)
                {
                    if(isset($value['label'])){
                        $temp_option['label'] = $value['label'];
                    }
                    $temp_option['optionPrice'] = $value['optionPrice'];
                    array_push($temp_value,$temp_option);
                }
                $temp_variation['values']= $temp_value;
                array_push($food_variations,$temp_variation);
            }
        }
        //combinations end
        $item->food_variations = json_encode($food_variations);
        $item->price = $request->price;
        $item->image =  $request->has('image') ? Helpers::upload('product/', 'png', $request->file('image')) : $newFileName ?? null;
        $item->available_time_starts = $request->available_time_starts;
        $item->available_time_ends = $request->available_time_ends;
        $item->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $item->discount_type = $request->discount_type;
        $item->maximum_cart_quantity = $request->maximum_cart_quantity;
        $item->attributes = $request->has('attribute_id') ? $request->attribute_id : json_encode([]);
        $item->add_ons = $request->has('addon_ids') ? json_encode(explode(',',$request->addon_ids)) : json_encode([]);
        $item->store_id = $request['vendor']->stores[0]->id;
        $item->veg = $request->veg;
        $item->module_id = $request['vendor']->stores[0]->module_id;
        $item->stock= $request->current_stock;
        $item->images = $images;
        $item->unit_id = $request->unit;
        $item->organic = $request->organic??0;
        $item->save();
        $item->tags()->sync($tag_ids);


        if ($request['vendor']->stores[0]->module->module_type == 'pharmacy') {
            $item_details = new PharmacyItemDetails();
            $item_details->item_id = $item->id;
            $item_details->common_condition_id = $request->condition_id;
            $item_details->is_basic = $request->basic ?? 0;
            $item_details->save();
        }

        foreach ($data as $key=>$i) {
            $data[$key]['translationable_type'] = 'App\Models\Item';
            $data[$key]['translationable_id'] = $item->id;
        }
        Translation::insert($data);

        $product_approval_datas = \App\Models\BusinessSetting::where('key', 'product_approval_datas')->first()?->value ?? '';
        $product_approval_datas =json_decode($product_approval_datas , true);
        if (Helpers::get_mail_status('product_approval') && data_get($product_approval_datas,'Add_new_product',null) == 1) {
            $this->store_temp_data($item, $request,$tag_ids);
            $item->is_approved = 0;
            $item->save();
            return response()->json(['message' => translate('messages.The_product_will_be_published_once_it_receives_approval_from_the_admin.')], 200);

        }


        return response()->json(['message'=>translate('messages.product_added_successfully')], 200);
    }

    public function status(Request $request)
    {
        if(!$request->vendor->stores[0]->item_section)
        {
            return response()->json([
                'errors'=>[
                    ['code'=>'unauthorized', 'message'=>translate('messages.permission_denied')]
                ]
            ],403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $product = Item::find($request->id);
        $product->status = $request->status;
        $product->save();

        return response()->json(['message' => translate('messages.product_status_updated')], 200);
    }

    public function get_item($id)
    {
        try {
            $item = Item::withoutGlobalScope('translate')->with('tags')->where('id',$id)
            ->first();
            $item = Helpers::product_data_formatting_translate($item, false, false, app()->getLocale());
            return response()->json($item, 200);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
            ], 404);
        }
    }

    public function update(Request $request)
    {
        if(!$request->vendor->stores[0]->item_section)
        {
            return response()->json([
                'errors'=>[
                    ['code'=>'unauthorized', 'message'=>translate('messages.permission_denied')]
                ]
            ],403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'category_id' => 'required',
            'price' => 'required|numeric|min:0.01',
            'discount' => 'required|numeric|min:0',

        ], [
            'category_id.required' => translate('messages.category_required'),
        ]);

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', translate('messages.discount_can_not_be_more_than_or_equal'));
        }
        $data = json_decode($request->translations, true);

        if (count($data) < 1) {
            $validator->getMessageBag()->add('translations', translate('messages.Name and description in english is required'));
        }

        if ($request['price'] <= $dis || count($data) < 1 || $validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 402);
        }
        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(",", $request->tags);
        }
        if(isset($tags)){
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                array_push($tag_ids,$tag->id);
            }
        }

        $p = Item::findOrFail($request->id);

        $p->name = $data[0]['value'];

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

        $p->category_id = $request->sub_category_id?$request->sub_category_id:$request->category_id;
        $p->category_ids = json_encode($category);
        $p->description = $data[1]['value'];

        $choice_options = [];
        if ($request->has('choice')) {
            foreach (json_decode($request->choice_no) as $key => $no) {
                $str = 'choice_options_' . $no;
                if (json_decode($request[$str])[0] == null) {
                    $validator->getMessageBag()->add('name', translate('messages.attribute_choice_option_value_can_not_be_null'));
                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $item['name'] = 'choice_' . $no;
                $item['title'] = json_decode($request->choice)[$key];
                $item['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', json_decode($request[$str]))));
                array_push($choice_options, $item);
            }
        }
        $p->choice_options = json_encode($choice_options);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach (json_decode($request->choice_no) as $key => $no) {
                $name = 'choice_options_' . $no;
                $my_str = implode('|', json_decode($request[$name]));
                array_push($options, explode(',', $my_str));
            }
        }
        //Generates the combinations of customer choice options
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $i) {
                    if ($k > 0) {
                        $str .= '-' . str_replace(' ', '', $i);
                    } else {
                        $str .= str_replace(' ', '', $i);
                    }
                }
                $i = [];
                $i['type'] = $str;
                $i['price'] = abs($request['price_' . str_replace('.', '_', $str)]);
                $i['stock'] = abs($request['stock_' . str_replace('.', '_', $str)]);
                array_push($variations, $i);
            }
        }
        //combinations end

        $images = $p['images'];

        foreach ($p['images'] as $img) {
            if (!in_array($img, json_decode($request->images, true))) {
                if(Storage::disk('public')->exists('product/' . $img))
                {
                    Storage::disk('public')->delete('product/' . $img);
                }
                $key = array_search($img, $images);
                unset($images[$key]);
            }
        }
        if ($request->has('item_images')){
            foreach ($request->item_images as $img) {
                $image = Helpers::upload('product/', 'png', $img);
                array_push($images, $image);
            }
        }




        $food_variations = [];
        if(isset($request->options))
        {
            foreach(json_decode($request->options,true) as $key=>$option)
            {
                $temp_variation['name']= $option['name'];
                $temp_variation['type']= $option['type'];
                $temp_variation['min']= $option['min'] ?? 0;
                $temp_variation['max']= $option['max'] ?? 0;
                $temp_variation['required']= $option['required']??'off';
                $temp_value = [];
                foreach($option['values'] as $value)
                {
                    if(isset($value['label'])){
                        $temp_option['label'] = $value['label'];
                    }
                    $temp_option['optionPrice'] = $value['optionPrice'];
                    array_push($temp_value,$temp_option);
                }
                $temp_variation['values']= $temp_value;
                array_push($food_variations,$temp_variation);
            }
        }

        // $variation_changed = false;
        // if(strcmp($p->food_variations,json_encode($food_variations)) !== 0 || strcmp($p->variations,json_encode($variations)) !== 0){
        //     $variation_changed = true;
        // }



        $variation_changed = false;
        if((($p->food_variations != null && $food_variations != '[]' ) && strcmp($p->food_variations,json_encode($food_variations)) !== 0 )|| (
            ($p->variations != null && $variations != '[]' ) && strcmp($p->variations,json_encode($variations)) !== 0)){
            $variation_changed = true;
        }




        $old_price =$p->price;


        $p->variations = json_encode($variations);
        $p->food_variations = json_encode($food_variations);
        $p->price = $request->price;
        $p->image = $request->has('image') ? Helpers::update('product/', $p->image, 'png', $request->file('image')) : $p->image;
        $p->available_time_starts = $request->available_time_starts;
        $p->available_time_ends = $request->available_time_ends;
        $p->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $p->discount_type = $request->discount_type;
        $p->maximum_cart_quantity = $request->maximum_cart_quantity;
        $p->attributes = $request->has('attribute_id') ? $request->attribute_id : json_encode([]);
        $p->add_ons = $request->has('addon_ids') ? json_encode(explode(',',$request->addon_ids)) : json_encode([]);
        $p->stock= $request->current_stock??0;
        $p->veg = $request->veg??0;
        $p->images = array_values($images);
        $p->unit_id = $request->unit;
        $p->organic = $request->organic??0;



        $product_approval_datas = \App\Models\BusinessSetting::where('key', 'product_approval_datas')->first()?->value ?? '';
        $product_approval_datas =json_decode($product_approval_datas , true);


        if (Helpers::get_mail_status('product_approval') && ((data_get($product_approval_datas,'Update_anything_in_product_details',null) == 1) || (data_get($product_approval_datas,'Update_product_price',null) == 1 && $old_price !=  $request->price) || ( data_get($product_approval_datas,'Update_product_variation',null) == 1 &&  $variation_changed)) )  {

            $this->store_temp_data($p, $request,$tag_ids, true);
            return response()->json(['message' => translate('your_product_added_for_approval')], 200);
        }


        if($request['vendor']->stores[0]->module->module_type == 'pharmacy'){
            DB::table('pharmacy_item_details')
                ->updateOrInsert(
                    ['item_id' => $p->id],
                    [
                        'common_condition_id' => $request->condition_id,
                        'is_basic' => $request->basic ?? 0,
                    ]
                );
        }

        $p->save();
        $p->tags()->sync($tag_ids);

        foreach ($data as $key=>$item) {
            Translation::updateOrInsert(
                ['translationable_type' => 'App\Models\Item',
                    'translationable_id' => $p->id,
                    'locale' => $item['locale'],
                    'key' => $item['key']],
                ['value' => $item['value']]
            );
        }

        return response()->json(['message'=>translate('messages.product_updated_successfully')], 200);
    }

    public function delete(Request $request)
    {
        if(!$request->vendor->stores[0]->item_section)
        {
            return response()->json([
                'errors'=>[
                    ['code'=>'unauthorized', 'message'=>translate('messages.permission_denied')]
                ]
            ],403);
        }


        if($request?->temp_product){
            $product = TempProduct::findOrFail($request->id);
        }
        else{
            $product = Item::findOrFail($request->id);
            $product?->temp_product?->translations()?->delete();
            $product?->temp_product()?->delete();
            $product?->carts()?->delete();
        }


        if($product->image)
        {
            if (Storage::disk('public')->exists('product/' . $product['image'])) {
                Storage::disk('public')->delete('product/' . $product['image']);
            }
        }
        $product->translations()->delete();
        $product->delete();

        return response()->json(['message'=>translate('messages.product_deleted_successfully')], 200);
    }

    public function search(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $key = explode(' ', $request['name']);

        $products = Item::active()
        ->with(['rating'])
        ->where('store_id', $request['vendor']->stores[0]->id)
        ->when($request->category_id, function($query)use($request){
            $query->whereHas('category',function($q)use($request){
                return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
            });
        })
        ->when($request->store_id, function($query) use($request){
            return $query->where('store_id', $request->store_id);
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->orWhereHas('tags',function($query)use($key){
                $query->where(function($q)use($key){
                    foreach ($key as $value) {
                        $q->where('tag', 'like', "%{$value}%");
                    };
                });
            });
        })
        ->limit(50)
        ->get();

        $data = Helpers::product_data_formatting($products, true, false, app()->getLocale());
        return response()->json($data, 200);
    }

    public function reviews(Request $request)
    {
        $id = $request['vendor']->stores[0]->id;;

        $reviews = Review::with(['customer', 'item'])
        ->whereHas('item', function($query)use($id){
            return $query->where('store_id', $id);
        })
        ->latest()->get();

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            $item['item_name'] = null;
            $item['item_image'] = null;
            $item['customer_name'] = null;
            if($item->item)
            {
                $item['item_name'] = $item->item->name;
                $item['item_image'] = $item->item->image;
                if(count($item->item->translations)>0)
                {
                    $translate = array_column($item->item->translations->toArray(), 'value', 'key');
                    $item['item_name'] = $translate['name'];
                }
            }

            if($item->customer)
            {
                $item['customer_name'] = $item->customer->f_name.' '.$item->customer->l_name;
            }

            unset($item['item']);
            unset($item['customer']);
            array_push($storage, $item);
        }

        return response()->json($storage, 200);
    }


    public function organic(Request $request)
    {
        if(!$request->vendor->stores[0]->item_section)
        {
            return response()->json([
                'errors'=>[
                    ['code'=>'unauthorized', 'message'=>translate('messages.permission_denied')]
                ]
            ],403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'organic' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $product = Item::find($request->id);
        $product->organic = $request->organic??0;
        $product->save();

        return response()->json(['message' => translate('messages.product_organic_status_updated')], 200);

    }


    public function recommended(Request $request)
    {
        if(!$request->vendor->stores[0]->item_section)
        {
            return response()->json([
                'errors'=>[
                    ['code'=>'unauthorized', 'message'=>translate('messages.permission_denied')]
                ]
            ],403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $product = Item::find($request->id);
        $product->recommended = $request->status;
        $product->save();

        return response()->json(['message' => translate('messages.product_recommended_status_updated')], 200);

    }

    public function store_temp_data($data, $request,$tag_ids , $update =null)
    {
        $item = TempProduct::firstOrNew(
            ['item_id' => $data->id]
        );

        $translated_data = json_decode($request->translations, true);

        $item->name = $translated_data[0]['value'];
        $item->description = $translated_data[1]['value'];
        $item->image = $data->image;
        $item->images = $data->images;

        $item->store_id = $data->store_id;
        $item->module_id = $data->module_id;
        $item->unit_id = $data->unit_id;
        $item->item_id = $data->id;

        $item->category_id = $data->category_id;
        $item->category_ids = $data->category_ids;
        $item->slug = $data->slug;

        $item->choice_options = $data->choice_options;
        $item->food_variations = $data->food_variations;
        $item->variations = $data->variations;
        $item->add_ons = $data->add_ons;
        $item->attributes = $data->attributes;

        $item->price = $data->price;
        $item->discount = $data->discount;
        $item->discount_type = $data->discount_type;
        $item->tag_ids =json_encode($tag_ids);

        $item->available_time_starts = $data->available_time_starts;
        $item->available_time_ends = $data->available_time_ends;
        $item->maximum_cart_quantity = $data->maximum_cart_quantity;
        $item->veg = $data->veg ?? 0;
        $item->organic = $data->organic ?? 0;
        $item->stock =  $data->stock ?? 0;
        $item->common_condition_id =  $request->condition_id ?? 0;
        $item->basic =  $request->basic ?? 0;


        if($update){
            $item->is_rejected = 0;
        }

        $item->save();
        if($request['vendor']->stores[0]->module->module_type == 'pharmacy'){
            DB::table('pharmacy_item_details')
                ->updateOrInsert(
                    ['temp_product_id' => $item->id],
                    [
                        'common_condition_id' => $request->condition_id,
                        'is_basic' => $request->basic ?? 0,
                        'item_id' => null
                    ]
                );
        }
        // Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'TempProduct', data_id: $item->id, data_value: $item->name);
        // Helpers::add_or_update_translations(request: $request, key_data: 'description', name_field: 'description', model_name: 'TempProduct', data_id: $item->id, data_value: $item->description);


        foreach ($translated_data as $key=>$translated) {
            Translation::updateOrInsert(
                ['translationable_type' => 'App\Models\TempProduct',
                    'translationable_id' => $item->id,
                    'locale' => $translated['locale'],
                    'key' => $translated['key']],
                ['value' => $translated['value']]
            );
        }

        return true;
    }


    public function pending_item_list(Request $request)
    {
        $key = explode(' ', $request['name']);
        $limit = $request['limite']??25;
        $offset = $request['offset']??1;

        $sub_category_id = $request->sub_category_id ?? 'all';
        $category_id = $request->category_id ??  'all';
        $type = $request->type  ??  'all';
        $status = $request->status  ??  'all';

        $items = TempProduct::
        when(is_numeric($category_id), function($query)use($category_id){
            return $query->whereHas('category',function($q)use($category_id){
                return $q->whereId($category_id)->orWhere('parent_id', $category_id);
            });
        })
        ->where('store_id',$request['vendor']->stores[0]->id)
        ->when(isset($key), function($q) use($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->where('name', 'like', "%{$value}%");
                }
            });
        })
        ->when(is_numeric($sub_category_id), function ($query) use ($sub_category_id) {
            return $query->where('category_id', $sub_category_id);
        })
        ->when($status == 'pending' , function($query){
            $query->where('is_rejected',0);
        })
        ->when($status == 'rejected' , function($query){
            $query->where('is_rejected',1);
        })

        ->type($type)->latest()->select(['id','name' ,'image','price','is_rejected','item_id','category_ids']);
        $storage = [];
        $categories = [];
        foreach($items->items() as $item){
            foreach (json_decode($item['category_ids']) as $value) {
                $category_name = Category::where('id',$value->id)->pluck('name');
                $categories[] = ['id' => (string)$value->id, 'position' => $value->position, 'name'=>data_get($category_name,'0','NA')];
            }
            $item['category_ids'] = $categories;
            array_push($storage, $item);
        }

        $data = [
            'total_size' => $items->total(),


            'items' =>  $storage,
        ];

        return response()->json($data,200);
    }

    public function requested_item_view($id){

        $product=TempProduct::withoutGlobalScope('translate')->with(['translations','store','unit'])->findOrFail($id);
        $product=  Helpers::product_data_formatting($product, false, false, app()->getLocale() , true);
        return response()->json($product,200);

    }
}
