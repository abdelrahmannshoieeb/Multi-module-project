<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Store;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $category =  Category::create(
            [
                'name' => 'supermarket',
                'image' => '2022-03-22-62396e9c2338a.png',
                'module_id' => 1,
                'parent_id' => 0,
                'position' => 1
            ],
        );

        Store::where('id', '<=', 11)->update(['category_id' => $category->id]);

        $category =  Category::create(
            [
                'name' => 'pharmacy',
                'image' => '2022-03-22-62396e9c2338a.png',
                'module_id' => 1,
                'parent_id' => 0,
                'position' => 1
            ],
        );

        Store::where('id', '>', 11)->where('id', '<=', 21)->update(['category_id' => $category->id]);

        $category =  Category::create(
            [
                'name' => 'household',
                'image' => '2022-03-22-62396e9c2338a.png',
                'module_id' => 1,
                'parent_id' => 0,
                'position' => 1
            ],
        );

        Store::where('id', '>', 21)->where('id', '<=', 31)->update(['category_id' => $category->id]);

        $category =  Category::create(
            [
                'name' => 'electronics',
                'image' => '2022-03-22-62396e9c2338a.png',
                'module_id' => 1,
                'parent_id' => 0,
                'position' => 1
            ],
        );

        Store::where('id', '>', 31)->update(['category_id' => $category->id]);
    }
}
