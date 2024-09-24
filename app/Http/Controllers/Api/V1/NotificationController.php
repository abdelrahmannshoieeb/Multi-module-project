<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;


class NotificationController extends Controller
{
    public function get_notifications(Request $request){

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => 'Zone id is required!']);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $zone_id= $request->header('zoneId');
        try {
            $notifications = Notification::active()->where('tergat', 'customer')->where(function($q)use($zone_id){
                $q->whereNull('zone_id')->orWhere('zone_id', $zone_id);
            })->where('updated_at', '>=', \Carbon\Carbon::today()->subDays(15))->get();
            $notifications->append('data');

            $user_notifications = UserNotification::where('user_id', $request->user()->id)->where('updated_at', '>=', \Carbon\Carbon::today()->subDays(15))->get();
            $notifications =  $notifications->merge($user_notifications);
            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function getUserActivities(Request $request)
    {
        // Validate the request
        $validator = Validator::make(array_merge($request->all(), ['user_id' => $request->query('user_id')]), [
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 400);
        }

        // Get the activity type from the request
        $activityType = $request->query('type');
        // Get the activity type from the request
        $user_id = $request->query('user_id');

        // Get the current time and calculate the time 24 hours ago
        $now = Carbon::now();
        $yesterday = $now->subDay();

        // Initialize query builder
        $newQuery = UserActivity::where('user_id', $user_id)->where('activity_date', '>=', $yesterday);
        $oldQuery = UserActivity::where('user_id', $user_id)->where('activity_date', '<', $yesterday);

        // Apply activity type filter if not 'all'
        if ($activityType !== 'all') {
            $newQuery->where('activity_type', $activityType);
            $oldQuery->where('activity_type', $activityType);
        }

        // Execute queries
        $newActivities = $newQuery->get();
        $oldActivities = $oldQuery->get();

        // Return the results as JSON
        return response()->json([
            'data' => [
                'new_activities' => $newActivities,
                'old_activities' => $oldActivities
            ]
        ]);
    }
}
