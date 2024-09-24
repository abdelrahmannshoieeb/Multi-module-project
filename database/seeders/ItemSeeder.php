<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $component = [
            [
                "name" => "calories",
                "values" => [
                        ["label" => "calories", 'amount' => '120 col', 'daily_value' => ''],
                        ["label" => "total fat", 'amount' => '4.5 g', 'daily_value' => '7 %'],
                        ["label" => "saturated", 'amount' => '3 g', 'daily_value' => '15 %'],
                        ["label" => "trans fat", 'amount' => '0 g', 'daily_value' => ''],
                        ["label" => "cholesterol", 'amount' => '4.5 g', 'daily_value' => '7 %'],
                        ["label" => "sodium", 'amount' => '4.5 g', 'daily_value' => '7 %'],
                        ["label" => "total carbohydrate", 'amount' => '4.5 g', 'daily_value' => '7 %'],
                    ]],
                ];

        $food_variants = [
                [
                    "name" => "Size",
                    "type" => "single",
                    "min" => 0,
                    "max" => 0,
                    "required" => "off",
                    "values" => [
                            ["label" => "small","optionPrice" => "100"],
                            ["label" => "large","optionPrice" => "200"]
                        ]
                ],

                [
                    "name" => "Extra",
                    "type" => "single",
                    "min" => 0,
                    "max" => 0,
                    "required" => "off",
                    "values" => [
                            ["label" => "cheese","optionPrice" => "100"],
                            ["label" => "cola","optionPrice" => "200"],
                            ["label" => "sous","optionPrice" => "200"]
                        ]
                ]
            ];

        $item = Item::where('id', 1)->first();

        $item->update(
            [
                'food_variations' => $food_variants,
                'component' => $component
            ]
        );

        
    }
}
