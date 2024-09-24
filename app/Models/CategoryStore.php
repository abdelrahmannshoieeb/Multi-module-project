<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryStore extends Model
{
    use HasFactory;

    public $fillable = ['category_id', 'store_id', 'module_id '];

    protected  $table = "category_store";

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
