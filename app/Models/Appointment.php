<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'booked_features',
        'number_of_patients',
        'date',
        'time',
        'module_id',
        'user_id',
        'full_name',
        'phone',
        'email',
        'payment_type',
        'total_orders',
    ];

    protected $casts = [
        'booked_features' => 'array',
    ];
}
