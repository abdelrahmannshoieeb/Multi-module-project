<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Discount;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for($i=0; $i<11; $i++){
            $discount = Discount::create([
                'start_date' => '2024-07-08',
                'end_date' => '2025-07-02',
                'start_time' => '23:48:36',
                'end_time' => '23:48:36',
                'min_purchase' => 100,
                'max_discount' => 60,
                'discount' => 50,
                'discount_type' => 'percentage',
                'store_id' => $i,
                'created_at' => '2024-07-08 12:56:30',
                'updated_at' => '2024-07-08 12:56:30'
            ]);
        }
    }
}
