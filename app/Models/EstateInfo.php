<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstateInfo extends Model
{
    use HasFactory;

    protected $fillable = ['area', 'city', 'rooms_number', 'bathrooms_number', 'item_id'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
