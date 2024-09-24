<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemWithRamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $variants = [
            [
                "name" => "size",
                "type" => "12 GB",
                'price' => 100,
                'stock' => 500
            ],

            [
                "name" => "size",
                "type" => "256 GB",
                'price' => 100,
                'stock' => 500
            ],

            [
                "name" => "size",
                "type" => "512 GB",
                'price' => 100,
                'stock' => 500
            ],

            [
                "name" => "ram",
                "type" => "4 G",
                'price' => 100,
                'stock' => 500
            ],

            [
                "name" => "ram",
                "type" => "8 G",
                'price' => 100,
                'stock' => 500
            ],

            [
                "name" => "size",
                "type" => "16 G",
                'price' => 100,
                'stock' => 500
            ],

        ];

    $item = Item::where('id', 2)->first();

    $item->update(
        [
            'variations' => $variants
        ]
    );

    }
}
