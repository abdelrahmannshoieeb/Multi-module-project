<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [ 
        'user_id',
        'guest_numbers',
        'reservation_date',
        'reservation_time',
        'notes',
        'where_to_seat',
    ];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}