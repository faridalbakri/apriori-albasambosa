<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_id' => fake()->uuid(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            // price set in configure() — excluded from $fillable for mass-assignment protection
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Cart $cart) {
            // copy price from product, not random — single source of truth
            if ($cart->price === null && $cart->product_id) {
                $product = Product::find($cart->product_id);
                $cart->price = $product ? (int) $product->price : fake()->numberBetween(3_000, 45_000);
            }
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id, 'session_id' => null]);
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(['session_id' => $sessionId, 'user_id' => null]);
    }
}
