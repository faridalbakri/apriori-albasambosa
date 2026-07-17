<?php

namespace Database\Seeders;

use App\Models\AdminPick;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminPickSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $products = Product::orderByDesc('total_sold')->take(5)->get();

        foreach ($products as $i => $product) {
            AdminPick::create([
                'product_id' => $product->id,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
