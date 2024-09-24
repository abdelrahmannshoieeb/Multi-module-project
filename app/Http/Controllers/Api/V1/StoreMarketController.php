<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Store;
use App\Models\Banner;
use App\Models\CategoryStore;
use App\Models\Item;
use Illuminate\Support\Facades\Validator;
use App\Models\OrderRequest;

class StoreMarketController extends Controller
{

    public function search_sub_category(Request $request){
        $subCategory_id = $request->subCategory_id;

        $module_ids_of_categories = Category::where("id",$subCategory_id)->pluck("module_id")->toArray();
        $banner = Banner::whereIn("module_id",$module_ids_of_categories)->get();
        $stores = Store::whereIn("module_id",$module_ids_of_categories)->with('discount')->get();

        return response()->json([
           "data" => [
            "stores" => $stores,
            "banners" => $banner,
           ]
        ]);


    }

    public function search_keyword(Request $request){
        $keyword = $request->keyword;
        $category_id = $request->category_id ?? 0;

        if($keyword && $category_id){
            $category_ids = Item::query()->where("name","like","%$keyword%")->where("category_id",$category_id)->pluck("category_id")->toArray();
            $stores = Store::query()->whereIn("category_id",$category_ids)->with("items")->get();
        }
        elseif(!$category_id)
        {
            $stores = Store::whereHas('items', function($q) use ($keyword){
                $q->where("name","like","%$keyword%");
            })->with('items')->get();
        }

        return response()->json([

            'status' => 'success',
            'stores' => $stores,

        ]);
    }

    public function get_categories(Request $request)
{
    $module_id = $request->module_id ?? 0;

    // Fetch categories that have associated stores which in turn have items
    $categories = Category::where('status', 1)
    ->where('module_id', $module_id)
    ->whereHas('products', function ($query) use ($module_id) {
        $query->where('module_id',$module_id);
    })
    ->get();

    return response(['data' => [
        'status' => 'success',
        'categories' => $categories,
    ]], 200);
}

    public function get_stores(Request $request)
    {
        $category_id = $request->category_id ?? 0;



        // $stores = Store::whereHas('categoryStore', function($q) use ($category_id){
        //     $q->where('category_id', $category_id);
        // })->get();
        $stores = Store::where('category_id', $category_id)->with('discount')->get();

        $modules = $stores->map(function($item) {
            return $item->module_id;
        });

        $banner = Banner::whereIn("module_id",$modules)->get();

        // $banner = Banner::where();

        return response(['data' => [
            'status' => 'success',
            'stores' => $stores,
            'banners' => $banner,
        ]], 200);
    }

    public function get_items_categories(Request $request)
    {
        $store_id = $request->store_id ?? 0;
        $categories = Category::whereHas('products', function($q) use ($store_id){
            $q->where('store_id', $store_id);
        })->get();

        return response(['data' => [
            'status' => 'success',
            'categories' => $categories,
        ]], 200);
    }

    // public function get_store_items(Request $request)
    // {
    //     $store_id = $request->store_id ?? 0;
    //     $category_id = $request->category_id ?? 0;
    //     $search = $request->search ?? null;

    //     if($category_id != 0)
    //     {
    //         $items = Item::where('category_id', $category_id)->whereHas('store', function($q) use ($store_id){
    //             $q->where('id', $store_id);
    //         })->get();
    //     }

    //     else{
    //         $items =$items = Item::whereHas('store', function($q) use ($store_id){
    //             $q->where('id', $store_id);
    //         })->get();
    //     }

    //     if($search){
    //         $items = Item::where('category_id', $category_id)->searchByName($search)->whereHas('store', function($q) use ($store_id){
    //             $q->where('id', $store_id);
    //         })->get();
    //     }

    //     return response(['data' => [
    //         'status' => 'success',
    //         'items' => $items,
    //     ]], 200);
    // }


    public function colorNameToHex($colour)
        {
            $colours = [
                "aliceblue" => "#f0f8ff",
                "antiquewhite" => "#faebd7",
                "aqua" => "#00ffff",
                "aquamarine" => "#7fffd4",
                "azure" => "#f0ffff",
                "beige" => "#f5f5dc",
                "bisque" => "#ffe4c4",
                "black" => "#000000",
                "blanchedalmond" => "#ffebcd",
                "blue" => "#0000ff",
                "blueviolet" => "#8a2be2",
                "brown" => "#a52a2a",
                "burlywood" => "#deb887",
                "cadetblue" => "#5f9ea0",
                "chartreuse" => "#7fff00",
                "chocolate" => "#d2691e",
                "coral" => "#ff7f50",
                "cornflowerblue" => "#6495ed",
                "cornsilk" => "#fff8dc",
                "crimson" => "#dc143c",
                "cyan" => "#00ffff",
                "darkblue" => "#00008b",
                "darkcyan" => "#008b8b",
                "darkgoldenrod" => "#b8860b",
                "darkgray" => "#a9a9a9",
                "darkgreen" => "#006400",
                "darkkhaki" => "#bdb76b",
                "darkmagenta" => "#8b008b",
                "darkolivegreen" => "#556b2f",
                "darkorange" => "#ff8c00",
                "darkorchid" => "#9932cc",
                "darkred" => "#8b0000",
                "darksalmon" => "#e9967a",
                "darkseagreen" => "#8fbc8f",
                "darkslateblue" => "#483d8b",
                "darkslategray" => "#2f4f4f",
                "darkturquoise" => "#00ced1",
                "darkviolet" => "#9400d3",
                "deeppink" => "#ff1493",
                "deepskyblue" => "#00bfff",
                "dimgray" => "#696969",
                "dodgerblue" => "#1e90ff",
                "firebrick" => "#b22222",
                "floralwhite" => "#fffaf0",
                "forestgreen" => "#228b22",
                "fuchsia" => "#ff00ff",
                "gainsboro" => "#dcdcdc",
                "ghostwhite" => "#f8f8ff",
                "gold" => "#ffd700",
                "goldenrod" => "#daa520",
                "gray" => "#808080",
                "green" => "#008000",
                "greenyellow" => "#adff2f",
                "honeydew" => "#f0fff0",
                "hotpink" => "#ff69b4",
                "indianred" => "#cd5c5c",
                "indigo" => "#4b0082",
                "ivory" => "#fffff0",
                "khaki" => "#f0e68c",
                "lavender" => "#e6e6fa",
                "lavenderblush" => "#fff0f5",
                "lawngreen" => "#7cfc00",
                "lemonchiffon" => "#fffacd",
                "lightblue" => "#add8e6",
                "lightcoral" => "#f08080",
                "lightcyan" => "#e0ffff",
                "lightgoldenrodyellow" => "#fafad2",
                "lightgrey" => "#d3d3d3",
                "lightgreen" => "#90ee90",
                "lightpink" => "#ffb6c1",
                "lightsalmon" => "#ffa07a",
                "lightskyblue" => "#87cefa",
                "lightslategray" => "#778899",
                "lightsteelblue" => "#b0c4de",
                "lightyellow" => "#ffffe0",
                "lime" => "#00ff00",
                "limegreen" => "#32cd32",
                "linen" => "#faf0e6",
                "magenta" => "#ff00ff",
                "maroon" => "#800000",
                "mediumaquamarine" => "#66cdaa",
                "mediumblue" => "#0000cd",
                "mediumorchid" => "#ba55d3",
                "mediumpurple" => "#9370d8",
                "mediumseagreen" => "#3cb371",
                "mediumslateblue" => "#7b68ee",
                "mediumspringgreen" => "#00fa9a",
                "mediumturquoise" => "#48d1cc",
                "mediumvioletred" => "#c71585",
                "midnightblue" => "#191970",
                "mintcream" => "#f5fffa",
                "mistyrose" => "#ffe4e1",
                "moccasin" => "#ffe4b5",
                "navajowhite" => "#ffdead",
                "navy" => "#000080",
                "oldlace" => "#fdf5e6",
                "olive" => "#808000",
                "olivedrab" => "#6b8e23",
                "orange" => "#ffa500",
                "orangered" => "#ff4500",
                "orchid" => "#da70d6",
                "palegoldenrod" => "#eee8aa",
                "palegreen" => "#98fb98",
                "paleturquoise" => "#afeeee",
                "palevioletred" => "#d87093",
                "papayawhip" => "#ffefd5",
                "peachpuff" => "#ffdab9",
                "peru" => "#cd853f",
                "pink" => "#ffc0cb",
                "plum" => "#dda0dd",
                "powderblue" => "#b0e0e6",
                "purple" => "#800080",
                "rebeccapurple" => "#663399",
                "red" => "#ff0000",
                "rosybrown" => "#bc8f8f",
                "royalblue" => "#4169e1",
                "saddlebrown" => "#8b4513",
                "salmon" => "#fa8072",
                "sandybrown" => "#f4a460",
                "seagreen" => "#2e8b57",
                "seashell" => "#fff5ee",
                "sienna" => "#a0522d",
                "silver" => "#c0c0c0",
                "skyblue" => "#87ceeb",
                "slateblue" => "#6a5acd",
                "slategray" => "#708090",
                "snow" => "#fffafa",
                "springgreen" => "#00ff7f",
                "steelblue" => "#4682b4",
                "tan" => "#d2b48c",
                "teal" => "#008080",
                "thistle" => "#d8bfd8",
                "tomato" => "#ff6347",
                "turquoise" => "#40e0d0",
                "violet" => "#ee82ee",
                "wheat" => "#f5deb3",
                "white" => "#ffffff",
                "whitesmoke" => "#f5f5f5",
                "yellow" => "#ffff00",
                "yellowgreen" => "#9acd32"
            ];

            $colourLower = strtolower($colour);
            if (array_key_exists($colourLower, $colours)) {
                return $colours[$colourLower];
            }

            return false;
        }

        public function get_store_items(Request $request)
    {
        $store_id = $request->store_id ?? 0;
        $category_id = $request->category_id ?? 0;
        $search = $request->search ?? null;

        if($category_id != 0)
        {
            $items = Item::where('category_id', $category_id)->whereHas('store', function($q) use ($store_id){
                $q->where('id', $store_id);
            })->get();
        }
        else{
            $items = Item::whereHas('store', function($q) use ($store_id){
                $q->where('id', $store_id);
            })->get();
        }

        if($search){
            $items = Item::where('category_id', $category_id)->searchByName($search)->whereHas('store', function($q) use ($store_id){
                $q->where('id', $store_id);
            })->get();
        }

        // // Convert the items to the correct data type using casts
        // $items = $items->map(function ($item) {
        //     return [
        //         'id' => (int) $item->id,
        //         'is_marketplace' => (int) $item->is_marketplace,
        //         'conditions' => $item->conditions,
        //         'name' => $item->name,
        //         'description' => $item->description,
        //         'image' => $item->image,
        //         'category_id' => (int) $item->category_id,
        //         'category_ids' => $item->category_ids,
        //         'variations' => $item->variations,
        //         'add_ons' => $item->add_ons,
        //         'attributes' => $item->attributes,
        //         'choice_options' => $item->choice_options,
        //         'price' => (float) $item->price,
        //         'tax' => (float) $item->tax,
        //         'tax_type' => $item->tax_type,
        //         'discount' => (float) $item->discount,
        //         'discount_type' => $item->discount_type,
        //         'available_time_starts' => $item->available_time_starts,
        //         'available_time_ends' => $item->available_time_ends,
        //         'veg' => (int) $item->veg,
        //         'status' => (int) $item->status,
        //         'store_id' => (int) $item->store_id,
        //         'created_at' => $item->created_at,
        //         'updated_at' => $item->updated_at,
        //         'order_count' => (int) $item->order_count,
        //         'avg_rating' => (float) $item->avg_rating,
        //         'rating_count' => (int) $item->rating_count,
        //         'rating' => $item->rating,
        //         'module_id' => (int) $item->module_id,
        //         'stock' => (int) $item->stock,
        //         'unit_id' => (int) $item->unit_id,
        //         'images' => $item->images,
        //         'food_variations' => $item->food_variations,
        //         'slug' => $item->slug,
        //         'recommended' => (int) $item->recommended,
        //         'organic' => (int) $item->organic,
        //         'maximum_cart_quantity' => (int) $item->maximum_cart_quantity,
        //         'is_approved' => (int) $item->is_approved,
        //         'type' => $item->type,
        //         'seller_name' => $item->seller_name,
        //         'seller_email' => $item->seller_email,
        //         'seller_whatsapp' => $item->seller_whatsapp,
        //         'seller_phoneNumber' => $item->seller_phoneNumber,
        //         'location' =>  $item->location ,
        //         'color' => $this->colorNameToHex($item->color),
        //         'option' => $item->option,
        //         'translations' => $item->translations->map(function ($translation) {
        //             return [
        //                 'id' => (int) $translation->id,
        //                 'translationable_type' => $translation->translationable_type,
        //                 'translationable_id' => (int) $translation->translationable_id,
        //                 'locale' => $translation->locale,
        //                 'key' => $translation->key,
        //                 'value' => $translation->value,
        //                 'created_at' => $translation->created_at,
        //                 'updated_at' => $translation->updated_at,
        //             ];
        //         })->toArray(),
        //     ];
        // });

        return response(['data' => [
            'status' => 'success',
            'items' => $items,
        ]], 200);
    }


    public function get(Request $request)
    {
        $store_id = $request->store_id;
    
        // Get the store by ID
        $store = Store::with('categories')->where('id', $store_id)->first();
    
        // Get categories that have items belonging to this store
        $categories = Category::where('status', 1)
            ->whereHas('products', function ($query) use ($store_id) {
                $query->where('store_id', $store_id);
            })
            ->get();
    
        return response(['data' => [
            'status' => 'success',
            'store' => $store,
            'categories' => $categories
        ]], 200);
    }
    


    public function get_sub_categories(Request $request)
    {
        $category_id = $request->category_id;

        $categories = Category::where('parent_id', $category_id)->get();

        return response( [
            'status' => 'success',
            'categories' => $categories,
        ], 200);
    }

    public function get_category_with_sub(Request $request)
    {
        $category_id = $request->category_id;
        $search = $request->search ?? null;

        $cat = Category::query()->where('id', $category_id);

        if($request->has('search')){
            $cat->where('name', 'like', "%$search%");
        }

        $last = $cat->with('childes.childes')->first();

        return response( [
            'status' => 'success',
            'categories' => $last,
        ], 200);
    }

    public function get_item(Request $request)
    {
        $item_id = $request->item_id ?? 0;

        $items = Item::where('id', $item_id)->wisth('store')->first();
        if($items){
            $similar_products = Item::where('id', '!=', $items->id)->where('category_id', $items->category_id)->wisth('store')->get();
            if($similar_products){

                return response(['data' => [
                    'status' => 'success',
                    'items' => $items,
                    'similar_products' => $similar_products
                ]], 200);
            }else{
                return response(['data' => [
                    'status' => 'success',
                    'items' => $items,
                    'similar_products' => NULL,
                ]], 200);
            }
        }

        return response(['data' => [
            'status' => 'success',
            'items' => 'Not found',
        ]], 404);

    }

    public function store_prescription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required | image',
            'national_id' => 'required| numeric',
            'client_name' => 'required| string',
            'client_phone' => 'required | string',
            'description' => 'nullable | string'
        ]);

        if($validator->fails())
        {
            return $validator->getMessageBag();
        }

        $image = $request->file('photo');

        $imageName = time().'.'.$image->extension();
        $path = 'orders/'.$imageName;
        $image->storeAs('orders', $imageName, 'public');

        OrderRequest::create(array_merge($request->all(), ['user_id' => auth()->user()?->id ?? 0, 'photo' => $path]));

        return response( [
            'status' => 'success',
        ], 200);
    }
}
