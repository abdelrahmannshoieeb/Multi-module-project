<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRequest extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'photo', 'national_id', 'client_name', 'client_phone', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
