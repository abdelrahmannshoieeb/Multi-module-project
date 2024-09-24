<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $variants = [
            [
                "name" => "choice_1",
                "title" => "Color",
                'options' => ['#CD5C5C', '#000000', '#800000']
            ],
        ];

        $item = Item::where('id', 2)->first();

        $item->update(
            [
                'choice_options' => $variants
            ]
        );
    }
}
