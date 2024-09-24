<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreReview extends Model
{
use HasFactory;
    protected $table = 'stores_reviews';

 
protected $fillable = [
'user_id', 'store_id', 'module_id', 'rating', 'notes'
];


public function users(){
    return $this->belongsTo(User::class,"user_id","id");
}


 
}
