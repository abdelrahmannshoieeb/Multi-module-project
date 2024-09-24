<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Item;
use Illuminate\Support\Facades\Validator;

class FoodController extends Controller
{
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required | string'
        ]);

        if($validator->fails())
        {
            return response()->json($validator->getMessageBag());
        }
        $keyword = $request->keyword ;
        $module_id = $request->module_id ?? 0;

        $stores = Store::where('module_id', $module_id)->with('items')->where('name', 'like', "%$keyword%")->get();
        $items = Item::where('module_id', $module_id)->where('name', 'like', "%$keyword%")->get();
 
        return response()->json([
            'status' => 'success',
            'key' => $keyword,
            'stores' => $stores,
            'items' => $items
        ]);


    }
}
