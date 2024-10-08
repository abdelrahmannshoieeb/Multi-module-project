<?php

namespace App\CentralLogics;

use Exception;
use App\Models\Store;
use App\Models\Review;
use App\Models\DataSetting;
use App\Models\StoreSchedule;

use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;

class StoreLogic
{
    public static function get_stores($zone_id, $filter_data, $type, $store_type, $featured=false, $longitude=0, $latitude=0, $filter=null, $rating_count=null)
    {
        $paginator = Store::withOpen($longitude??0, $latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount' => function($q){
                return $q->validate();
            }])
            ->whereHas('module', function($query){
                $query->active();
            })
            ->when($filter_data == 'delivery', function($q){
                return $q->delivery();
            })
            ->when($filter_data == 'take_away', function($q){
                return $q->takeaway();
            })
            ->when($featured, function($query){
                $query->featured();
            });

        if(config('module.current_module_data')) {
            $paginator = $paginator->whereHas('zone.modules', function($query){
                $query->where('modules.id', config('module.current_module_data')['id']);
            })->module(config('module.current_module_data')['id'])
            ->when(!config('module.current_module_data')['all_zone_service'], function($query) use ($zone_id){
                $query->where('zone_id', $zone_id);
            });
        } else {
            $paginator = $paginator->where('zone_id', $zone_id);
        }

        $paginator = $paginator->Active()
            ->type($type)
            ->when($store_type == 'all', function($q){
                return $q->orderBy('open', 'desc')
                    ->orderBy('distance');
            })
            ->when($store_type == 'newly_joined', function($q){
                return $q->latest();
            })
            ->when($store_type == 'popular', function($q){
                return $q->withCount('orders')
                    ->orderBy('orders_count', 'desc');
            })
            ->when($rating_count, function($query) use ($rating_count){
                $query->selectSub(function ($query) use ($rating_count){
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->when($filter && in_array('top_rated', $filter), function ($query){
                $query->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('popular', $filter), function ($query){
                $query->withCount('orders')->orderBy('orders_count', 'desc');
            })
            ->when($filter && in_array('discounted', $filter), function ($query){
                $query->where(function ($q) {
                    $q->whereHas('items', function ($q) {
                        $q->Discounted();
                    });
                });
            })
            ->when($filter && in_array('open', $filter), function ($query){
                $query->orderBy('open', 'desc');
            })
            ->when($filter && in_array('nearby', $filter), function ($query){
                $query->orderBy('distance');
            })
            ->orderBy('open', 'desc');

        $paginator->each(function ($store) {
            $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('CAST(categories.id AS UNSIGNED) as id, categories.parent_id')
                ->where('items.store_id', $store->id)
                ->where('categories.status', 1)
                ->groupBy('id', 'categories.parent_id')
                ->get();

            $data = json_decode($category_ids, true);

            $mergedIds = [];

            foreach ($data as $item) {
                if ($item['id'] != 0) {
                    $mergedIds[] = $item['id'];
                }
                if ($item['parent_id'] != 0) {
                    $mergedIds[] = $item['parent_id'];
                }
            }

            $category_ids = array_values(array_unique($mergedIds));

            $store->category_ids = $category_ids;

            $store->discount_status = !empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        });

        return [
            'stores' => $paginator->get()
        ];
    }

    public static function get_latest_stores($zone_id, $type = 'all', $longitude = 0, $latitude = 0, $module_id = null)
    {
        $zone_ids = json_decode($zone_id, true);

        $paginator = Store::withOpen($longitude ?? 0, $latitude ?? 0)
            ->withCount(['items', 'campaigns'])
            ->with(['discount' => function($q) {
                return $q->validate();
            }])
            ->when($module_id, function($query) use ($zone_ids, $module_id) {
                $query->whereHas('zone.modules', function($query) use ($module_id) {
                    $query->where('modules.id', $module_id);
                })->module($module_id);
                if (!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', $zone_ids);
                }
            })
            ->Active()
            ->type($type)
            ->latest();

        return [
            'stores' => $paginator->get()
        ];
    }


    public static function get_popular_stores($zone_id,  $type = 'all',$longitude=0,$latitude=0)
    {
        $paginator = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type)
            ->withCount('orders')
            ->orderBy('open', 'desc')
            ->orderBy('distance')
            ->orderBy('orders_count', 'desc')
            ;

        return [


            'stores' => $paginator->get()
        ];
    }

    public static function get_discounted_stores($zone_id,  $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null)
    {
        $paginator = Store::withOpen($longitude??0,$latitude??0)
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->where(function ($query) {
                $query->whereHas('items', function ($q) {
                    $q->Discounted();
                });
            })
            ->Active()
            ->type($type)
            ->when($rating_count, function($query) use ($rating_count){
                $query->selectSub(function ($query) use ($rating_count){
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                $qurey->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('popular',$filter),function ($qurey){
                $qurey->withCount('orders')->orderBy('orders_count', 'desc');
            })
            ->when($filter && in_array('open',$filter),function ($qurey){
                $qurey->orderBy('open', 'desc');
            })
            ->when($filter && in_array('nearby',$filter),function ($qurey){
                $qurey->orderBy('distance');
            })
            ->orderBy('open', 'desc')
            ;

        $paginator->each(function ($store) {
            $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id
            ')
                ->where('items.store_id', $store->id)
                ->where('categories.status', 1)
                ->groupBy('id', 'categories.parent_id')
                ->get();

            $data = json_decode($category_ids, true);

            $mergedIds = [];

            foreach ($data as $item) {
                if ($item['id'] != 0) {
                    $mergedIds[] = $item['id'];
                }
                if ($item['parent_id'] != 0) {
                    $mergedIds[] = $item['parent_id'];
                }
            }

            $category_ids = array_values(array_unique($mergedIds));

            $store->category_ids = $category_ids;

            $store->discount_status = !empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        });

        return [


            'stores' => $paginator->get()
        ];
    }

    public static function get_top_rated_stores($zone_id,  $type = 'all',$longitude=0,$latitude=0)
    {
        $paginator = Store::withOpen($longitude??0,$latitude??0)->whereNotNull('rating')
            ->withCount(['items','campaigns'])
            ->with(['discount'=>function($q){
                return $q->validate();
            }])
            ->when(config('module.current_module_data'), function($query)use($zone_id){
                $query->whereHas('zone.modules', function($query){
                    $query->where('modules.id', config('module.current_module_data')['id']);
                })->module(config('module.current_module_data')['id']);
                if(!config('module.current_module_data')['all_zone_service']) {
                    $query->whereIn('zone_id', json_decode($zone_id, true));
                }
            })
            ->Active()
            ->type($type)
            ->whereRaw("LENGTH(rating) > 0")
            ;

        return [


            'stores' => $paginator->get()
        ];
    }

    public static function get_store_details($store_id,$longitude=0,$latitude=0)
    {
        return Store::withOpen($longitude??0,$latitude??0)->with(['discount'=>function($q){
            return $q->validate();
        }, 'campaigns', 'schedules','activeCoupons'])
            ->withCount(['items','campaigns'])
            ->when(config('module.current_module_data'), function($query){
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($store_id),function ($qurey) use($store_id){
                $qurey->where('id', $store_id);
            })
            ->when(!is_numeric($store_id),function ($qurey) use($store_id){
                $qurey->where('slug', $store_id);
            })
            ->first();
    }

    public static function calculate_store_rating($ratings)
    {
        $total_submit = $ratings[0]+$ratings[1]+$ratings[2]+$ratings[3]+$ratings[4];
        $positive_submit = $ratings[0]+$ratings[1]+$ratings[2];
        $rating = ($ratings[0]*5+$ratings[1]*4+$ratings[2]*3+$ratings[3]*2+$ratings[4])/($total_submit?$total_submit:1);
        $positive_rating = $total_submit>0?(($positive_submit*100)/$total_submit):0;
        return ['rating'=>$rating, 'total'=>$total_submit, 'positive_rating'=>$positive_rating];
    }

    public static function update_store_rating($ratings, $product_rating)
    {
        $store_ratings = [1=>0 , 2=>0, 3=>0, 4=>0, 5=>0];
        if($ratings)
        {
            $store_ratings[1] = $ratings[4];
            $store_ratings[2] = $ratings[3];
            $store_ratings[3] = $ratings[2];
            $store_ratings[4] = $ratings[1];
            $store_ratings[5] = $ratings[0];
            $store_ratings[$product_rating] = $ratings[5-$product_rating] + 1;
        }
        else
        {
            $store_ratings[$product_rating] = 1;
        }
        return json_encode($store_ratings);
    }

    public static function search_stores($name, $zone_id, $category_id= null, $type = 'all',$longitude=0,$latitude=0,$filter=null,$rating_count=null)
    {
        $key = explode(' ', $name);
        $paginator = Store::withOpen($longitude??0,$latitude??0)
        ->whereHas('zone.modules', function($query){
            $query->where('modules.id', config('module.current_module_data')['id']);
        })
        ->withCount(['items','campaigns'])->with(['discount'=>function($q){
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
            ->when($category_id, function($query)use($category_id){
                $query->whereHas('items.category', function($q)use($category_id){
                    return $q->whereId($category_id)->orWhere('parent_id', $category_id);
                });
            })
            ->active()
            ->when($rating_count, function($query) use ($rating_count){
                $query->selectSub(function ($query) use ($rating_count){
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('items', 'items.id', '=', 'reviews.item_id')
                        ->whereColumn('items.store_id', 'stores.id')
                        ->groupBy('items.store_id')
                        ->havingRaw('AVG(reviews.rating) >= ?', [$rating_count]);
                }, 'avg_r')->having('avg_r', '>=', $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                $qurey->whereNotNull('rating')->whereRaw("LENGTH(rating) > 0");
            })
            ->when($filter && in_array('popular',$filter),function ($qurey){
                $qurey->withCount('orders')->orderBy('orders_count', 'desc');
            })
            ->when($filter && in_array('discounted',$filter),function ($qurey){
                $qurey->where(function ($query) {
                    $query->whereHas('items', function ($q) {
                        $q->Discounted();
                    });
                });
            })
            ->when($filter && in_array('open',$filter),function ($qurey){
                $qurey->orderBy('open', 'desc');
            })
            ->when($filter && in_array('nearby',$filter),function ($qurey){
                $qurey->orderBy('distance');
            })
            ->orderBy('open', 'desc')
            ->type($type);


        $paginator->each(function ($store) {
            $category_ids = DB::table('items')
                ->join('categories', 'items.category_id', '=', 'categories.id')
                ->selectRaw('
                CAST(categories.id AS UNSIGNED) as id,
                categories.parent_id
            ')
                ->where('items.store_id', $store->id)
                ->where('categories.status', 1)
                ->groupBy('id', 'categories.parent_id')
                ->get();

            $data = json_decode($category_ids, true);

            $mergedIds = [];

            foreach ($data as $item) {
                if ($item['id'] != 0) {
                    $mergedIds[] = $item['id'];
                }
                if ($item['parent_id'] != 0) {
                    $mergedIds[] = $item['parent_id'];
                }
            }

            $category_ids = array_values(array_unique($mergedIds));

            $store->category_ids = $category_ids;
            $store->discount_status = !empty($store->items->where('discount', '>', 0));
            unset($store['items']);
        });

        return [



            'stores' => $paginator->get()
        ];
    }

    public static function get_overall_rating($reviews)
    {
        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $key => $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }

    public static function get_earning_data($vendor_id)
    {
        $monthly_earning = OrderTransaction::whereMonth('created_at', date('m'))->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');
        $weekly_earning = OrderTransaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');
        $daily_earning = OrderTransaction::whereDate('created_at', now())->NotRefunded()->where('vendor_id', $vendor_id)->sum('store_amount');

        return['monthely_earning'=>(float)$monthly_earning, 'weekly_earning'=>(float)$weekly_earning, 'daily_earning'=>(float)$daily_earning];
    }

    public static function format_export_stores($stores)
    {
        $storage = [];
        foreach($stores as $item)
        {
            if($item->stores->count()<1)
            {
                break;
            }
            $storage[] = [
                'id'=>$item->stores[0]->id,
                'ownerId'=>$item->id,
                'ownerFirstName'=>$item->f_name,
                'ownerLastName'=>$item->l_name,
                'storeName'=>$item->stores[0]->name,
                'phone'=>$item->phone,
                'email'=>$item->email,
                'logo'=>$item->stores[0]->logo,
                'CoverPhoto'=>$item->stores[0]->cover_photo,
                'latitude'=>$item->stores[0]->latitude,
                'longitude'=>$item->stores[0]->longitude,
                'Address'=>$item->stores[0]->address ?? null,
                'zone_id'=>$item->stores[0]->zone_id,
                'module_id'=>$item->stores[0]->module_id,
                'MinimumOrderAmount'=>$item->stores[0]->minimum_order,
                'Comission'=>$item->stores[0]->comission ?? 0,
                'Tax'=>$item->stores[0]->tax ?? 0,
                'DeliveryTime'=>$item->stores[0]->delivery_time ?? '20-30',
                'MinimumDeliveryFee'=>$item->stores[0]->minimum_shipping_charge ?? 0,
                'PerKmDeliveryFee'=>$item->stores[0]->per_km_shipping_charge ?? 0,
                'MaximumDeliveryFee'=>$item->stores[0]->maximum_shipping_charge ?? 0,
                'ScheduleOrder'=> $item->stores[0]->schedule_order == 1 ? 'yes' : 'no',
                'Status'=> $item->stores[0]->status == 1 ? 'active' : 'inactive',
                'SelfDeliverySystem'=> $item->stores[0]->self_delivery_system == 1 ? 'active' : 'inactive',
                'Veg'=> $item->stores[0]->veg == 1 ? 'yes' : 'no',
                'NonVeg'=> $item->stores[0]->non_veg == 1 ? 'yes' : 'no',
                'FreeDelivery'=> $item->stores[0]->free_delivery == 1 ? 'yes' : 'no',
                'TakeAway'=> $item->stores[0]->take_away == 1 ? 'yes' : 'no',
                'Delivery'=> $item->stores[0]->delivery == 1 ? 'yes' : 'no',
                'ReviewsSection'=> $item->stores[0]->reviews_section == 1 ? 'active' : 'inactive',
                'PosSystem'=> $item->stores[0]->pos_system == 1 ? 'active' : 'inactive',
                'storeOpen'=> $item->stores[0]->active == 1 ? 'yes' : 'no',
                'FeaturedStore'=> $item->stores[0]->featured == 1 ? 'yes' : 'no',
            ];
        }

        return $storage;
    }

    public static function insert_schedule(int $store_id, array $days=[0,1,2,3,4,5,6], String $opening_time='00:00:00', String $closing_time='23:59:59')
    {
        $data = array_map(function($item)use($store_id, $opening_time, $closing_time){
            return     ['store_id'=>$store_id,'day'=>$item,'opening_time'=>$opening_time,'closing_time'=>$closing_time];
        },$days);
        try{
            StoreSchedule::upsert($data,['store_id','day','opening_time','closing_time']);
            return true;
        }catch(Exception $e)
        {
            return $e;
        }
        return false;

    }

    public static function format_store_sales_export_data($items)
    {
        $data = [];
        foreach($items as $key=>$item)
        {

            $data[]=[
                '#'=>$key+1,
                translate('messages.name')=>$item->name,
                translate('messages.quantity')=>$item->orders->sum('quantity'),
                translate('messages.gross_sale')=>$item->orders->sum('price'),
                translate('messages.discount_given')=>$item->orders->sum('discount_on_item'),

            ];
        }
        return $data;
    }

    public static function format_store_summary_export_data($stores)
    {
        $data = [];
        foreach($stores as $key=>$store)
        {
            $delivered = $store->orders->where('order_status', 'delivered')->count();
            $canceled = $store->orders->where('order_status', 'canceled')->count();
            $refunded = $store->orders->where('order_status', 'refunded')->count();
            $total = $store->orders->count();
            $refund_requested = $store->orders->whereNotNull('refund_requested')->count();
            $data[]=[
                '#'=>$key+1,
                translate('Store')=>$store->name,
                translate('Total Order')=>$total,
                translate('Delivered Order')=>$delivered,
                translate('Total Amount')=>$store->orders->where('order_status','delivered')->sum('order_amount'),
                translate('Completion Rate')=>($store->orders->count() > 0 && $delivered > 0)? number_format((100*$delivered)/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Ongoing Rate')=>($store->orders->count() > 0 && $delivered > 0)? number_format((100*($store->orders->count()-($delivered+$canceled)))/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Cancelation Rate')=>($store->orders->count() > 0 && $canceled > 0)? number_format((100*$canceled)/$store->orders->count(), config('round_up_to_digit')): 0,
                translate('Refund Request')=>$refunded,

            ];
        }
        return $data;
    }

    public static function get_recommended_stores($zone_id, $type = 'all', $longitude = 0, $latitude = 0, $module_id = null)
{
    $shuffle = null;
    if ($module_id) {
        $shuffle = DataSetting::where(['key' => 'shuffle_recommended_store', 'type' => $module_id])->first()?->value;
    }

    $zone_ids = json_decode($zone_id, true);

    $query = Store::withOpen($longitude ?? 0, $latitude ?? 0)
        ->withCount(['items', 'campaigns'])
        ->whereHas('storeConfig', function ($q) {
            $q->where(['is_recommended_deleted' => 0, 'is_recommended' => 1]);
        });

    if ($module_id) {
        $query->whereHas('zone', function ($query) use ($module_id, $zone_ids) {
            $query->where('zone.modules', function ($query) use ($module_id) {
                $query->where('modules.id', $module_id);
            });
            if (!config('module.current_module_data')['all_zone_service']) {
                $query->whereIn('zone_id', $zone_ids);
            }
        });
    }

    $query->Active()
        ->type($type);

    if ($shuffle == 1) {
        $query->inRandomOrder();
    }

    $stores = $query->get();

    return [
        'stores' => $stores
    ];
}

}
