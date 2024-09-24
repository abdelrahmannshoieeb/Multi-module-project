<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Store;

class TopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::where('id', '<=', 11)->update(['category_type' => 'supermarket']);

        Store::where('id', '>', 11)->where('id', '<=', 21)->update(['category_type' => 'pharmacy']);

        Store::where('id', '>', 21)->where('id', '<=', 31)->update(['category_type' => 'household']);

        Store::where('id', '>', 31)->update(['category_type' => 'electronics']);
    }
}
