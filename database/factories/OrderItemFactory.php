<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        return [
            'order_id' => Order::factory(),
            'product_id' => $product->id,
            'quantity' => fake()->numberBetween(1, 5),
            // price excluded from $fillable — set via forceFill in configure()
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item) {
            // price copied from product, not fillable — forceFill required
            if ($item->price === null && $item->product_id) {
                $product = Product::find($item->product_id);
                $item->forceFill(['price' => $product ? (int) $product->price : 0]);
            }
        });
    }
}
