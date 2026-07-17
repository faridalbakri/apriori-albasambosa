<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @var array<string> */
    protected static array $paymentMethods = [
        'bank_transfer_bca',
        'bank_transfer_bni',
        'bank_transfer_bri',
        'gopay',
        'qris',
        'shopeepay',
    ];

    public function definition(): array
    {
        return [
            'user_id' => null,
            'order_number' => sprintf(
                'ALBA-%s-%03d',
                now()->format('Ymd'),
                fake()->unique()->numberBetween(1, 999),
            ),
            'total_price' => 0, // calculated in configure() from items
            'status' => OrderStatus::Pending->value,
            'shipping_cost' => 0,
            'payment_method' => fake()->randomElement(self::$paymentMethods),
            'pickup_time' => null,
            'phone' => '+628'.fake()->numerify('##########'),
            'recipient_name' => fake()->name(),
            'address_detail' => null,
            'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            if ($order->total_price > 0) {
                return; // already set by caller
            }

            // recalculate total from items — single source of truth
            $sum = $order->items()->sum(\DB::raw('price * quantity'));
            $order->forceFill(['total_price' => $sum + (int) $order->shipping_cost])->save();
        });
    }

    /**
     * Attach items to the order after creation.
     *
     * Usage: Order::factory()->withItems(3)->create()
     */
    public function withItems(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            $products = Product::inRandomOrder()->take($count)->get();

            if ($products->isEmpty()) {
                $products = Product::factory($count)->create();
            }

            foreach ($products as $product) {
                (new OrderItem)->forceFill([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => fake()->numberBetween(1, 5),
                    'price' => (int) $product->price,
                ])->save();
            }

            $sum = $order->items()->sum(\DB::raw('price * quantity'));
            $order->forceFill(['total_price' => $sum + (int) $order->shipping_cost])->save();
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(['status' => $status->value]);
    }

    public function pickup(): static
    {
        // realistic pickup slots — 9:00 to 18:00, 1-7 days ahead
        $day = fake()->numberBetween(1, 7);
        $hour = fake()->numberBetween(9, 18);

        return $this->state([
            'pickup_time' => now()->addDays($day)->setHour($hour)->setMinute(0)->setSecond(0),
            'address_detail' => null,
        ]);
    }

    public function delivery(): static
    {
        return $this->state([
            'pickup_time' => null,
            'address_detail' => fake()->address(),
        ]);
    }
}
