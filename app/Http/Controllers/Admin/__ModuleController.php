<?php

namespace App\Http\Controllers\Admin;

use App\Models\Module;
use App\Models\Translation;
use Illuminate\Http\Request;
use App\Exports\ModuleExport;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\Storage;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $key = explode(' ', $request['search']);

        $modules = Module::withCount('stores')
        ->when(isset($key) , function($q) use($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('module_name', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate(config('default_pagination'));


        return view('admin-views.module.index',compact('modules'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return view('admin-views.module.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'module_name' => 'required|unique:modules|max:100',
            'module_type'=>'required',
//            'theme'=>'required_unless:module_type,parcel',
            'module_name.0' => 'required',
            'description.0' => 'required',
        ], [
            'module_name.required' => translate('messages.Name is required!'),
            'module_name.0.required'=>translate('default_name_is_required'),
            'description.0.required'=>translate('default_description_is_required'),
        ]);

        $module = new Module();
        $module->module_name = $request->module_name[array_search('default', $request->lang)];
        $module->icon = Helpers::upload('module/', 'png', $request->file('icon'));
        $module->thumbnail = Helpers::upload('module/', 'png', $request->file('thumbnail'));
        $module->module_type= $request->module_type;
        $module->theme_id= 1;
        $module->description= $request->description[array_search('default', $request->lang)];
        // $module->all_zone_service = $request->all_zone_service??false;
        $module->save();

        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());


       \DB::table('stores')
       ->where('module_id', $module->id)
       ->update(['module_name_en' => $module->module_name]);

        \DB::table('stores')
       ->where('module_id', $module->id)
       ->update(['module_name_ar' => $module->module_name]);


        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->module_name[$index])){
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Module',
                        'translationable_id' => $module->id,
                        'locale' => $key,
                        'key' => 'module_name',
                        'value' => $module->module_name,
                    ));
                }
            }else{
                if ($request->module_name[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Module',
                        'translationable_id' => $module->id,
                        'locale' => $key,
                        'key' => 'module_name',
                        'value' => $request->module_name[$index],
                    ));
                }
            }

            if($default_lang == $key && !($request->description[$index])){
                if (isset($module->description) && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Module',
                        'translationable_id' => $module->id,
                        'locale' => $key,
                        'key' => 'description',
                        'value' => $module->description,
                    ));
                }
            }else{
                if ($request->description[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\Module',
                        'translationable_id' => $module->id,
                        'locale' => $key,
                        'key' => 'description',
                        'value' => $request->description[$index],
                    ));
                }
            }
        }

        if(count($data))
        {
            Translation::insert($data);
        }

        Toastr::success(translate('messages.module_created_successfully'));
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $module = Module::findOrFail($id);
        return response()->json(['data'=>config('module.'.$module->module_type),'type'=>$module->module_type]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if(env('APP_MODE')=='demo' && in_array($id, [1,2,3,4,5]))
        {
            Toastr::warning(translate('messages.you_can_not_edit_this_module_please_add_a_new_module_to_edit'));
            return back();
        }

        $module = Module::withoutGlobalScope('translate')->findOrFail($id);
        return view('admin-views.module.edit', compact('module'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if(env('APP_MODE')=='demo' && in_array($id, [1,2,3,4,5]))
        {
            Toastr::warning(translate('messages.you_can_not_edit_this_module_please_add_a_new_module_to_edit'));
            return back();
        }

        $request->validate([
            'module_name' => 'required|max:100|unique:modules,module_name,'.$id,
//            'theme'=>'required_unless:module_type,parcel',
            'module_name.0' => 'required',
            'description.0' => 'required',
        ], [
            'module_name.required' => translate('messages.Name is required!'),
            'module_name.0.required'=>translate('default_name_is_required'),
            'description.0.required'=>translate('default_description_is_required'),
        ]);
        $module = Module::withoutGlobalScope('translate')->findOrFail($id);

        $module->module_name = $request->module_name[array_search('default', $request->lang)];
        $module->icon = $request->has('icon') ? Helpers::update('module/', $module->icon, 'png', $request->file('icon')) : $module->icon;
        $module->thumbnail = $request->has('thumbnail') ? Helpers::update('module/', $module->thumbnail, 'png', $request->file('thumbnail')) : $module->thumbnail;
        $module->theme_id= 1;
        $module->module_name_en= $request->module_name_en ;
        $module->module_name_ar= $request->module_name_ar;

        $module->description =  $request->description[array_search('default', $request->lang)];
        $module->all_zone_service = false;
        $module->save();

        $default_lang = str_replace('_', '-', app()->getLocale());

        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->module_name[$index])){
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Module',
                            'translationable_id' => $module->id,
                            'locale' => $key,
                            'key' => 'module_name'
                        ],
                        ['value' => $module->module_name]
                    );
                }
            }else{

                if ($request->module_name[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Module',
                            'translationable_id' => $module->id,
                            'locale' => $key,
                            'key' => 'module_name'
                        ],
                        ['value' => $request->module_name[$index]]
                    );
                }
            }
            if($default_lang == $key && !($request->description[$index])){
                if (isset($module->description) && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Module',
                            'translationable_id' => $module->id,
                            'locale' => $key,
                            'key' => 'description'
                        ],
                        ['value' => $module->description]
                    );
                }

            }else{

                if ($request->description[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\Module',
                            'translationable_id' => $module->id,
                            'locale' => $key,
                            'key' => 'description'
                        ],
                        ['value' => $request->description[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('messages.module_updated_successfully'));
        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $module = Module::withoutGlobalScope('translate')->findOrFail($id);
        if($module->thumbnail)
        {
            if (Storage::disk('public')->exists('module/' . $module['thumbnail'])) {
                Storage::disk('public')->delete('module/' . $module['thumbnail']);
            }
        }
        $module->translations()->delete();
        $module->delete();
        Toastr::success(translate('messages.module_deleted_successfully'));
        return back();
    }

    public function status(Request $request)
    {
        if(env('APP_MODE')=='demo' && in_array($request->id, [1,2,3,4,5]))
        {
            Toastr::warning(translate('messages.you_can_not_edit_this_module_please_add_a_new_module_to_edit'));
            return back();
        }
        $module = Module::find($request->id);
        $module->status = $request->status;
        $module->save();
        Toastr::success(translate('messages.module_status_updated'));
        return back();
    }

    public function type(Request $request)
    {
        return response()->json(['data'=>config('module.'.$request->module_type)]);
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $modules=Module::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('module_name', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('admin-views.module.partials._table',compact('modules'))->render(),
            'count'=>$modules->count()
        ]);
    }

    public function export(Request $request){
        $key = explode(' ', $request['search']);
        $collection = Module::withCount('stores')->
        when(isset($key) , function($q) use($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('module_name', 'like', "%{$value}%");
                }
            });
        })
        ->get();

        $data=[
            'data' =>$collection,
            'search' =>$request['search'] ?? null,
        ];
        if($request->type == 'csv'){
            return Excel::download(new ModuleExport($data), 'Module.csv');
        }
        return Excel::download(new ModuleExport($data), 'Module.xlsx');
    }
}
