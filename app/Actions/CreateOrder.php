<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateOrder
{
    /**
     * Create an order from cart items within a DB transaction.
     *
     * Reserves stock, generates order number, clears cart.
     *
     * @param  Collection<int, Cart>  $cartItems
     */
    public function __invoke(
        Collection $cartItems,
        ?User $user,
        string $shippingMethod,
        ?string $pickupTime,
        string $customerName,
        string $phone,
        float $shippingCost,
        ?string $addressDetail,
        ?string $postalCode = null,
    ): Order {
        if ($cartItems->isEmpty()) {
            throw new \InvalidArgumentException('Keranjang belanja kosong.');
        }

        return DB::transaction(function () use (
            $cartItems, $user, $shippingMethod, $pickupTime,
            $customerName, $phone, $shippingCost, $addressDetail, $postalCode
        ) {
            $totalPrice = 0;

            // Reserve stock for each cart item
            foreach ($cartItems as $cart) {
                $product = Product::where('id', $cart->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $available = $product->stock - $product->stock_reserved;

                if ($cart->quantity > $available) {
                    throw new \RuntimeException(
                        "Stok {$product->name} tidak mencukupi. Tersedia: {$available}."
                    );
                }

                $product->increment('stock_reserved', $cart->quantity);
                $totalPrice += $cart->price * $cart->quantity;
            }

            $totalPrice += $shippingCost;

            // Create order (protected fields set individually per mass-assignment rules)
            $order = new Order;
            $order->fill([
                'payment_method' => 'midtrans_snap',
                'pickup_time' => $shippingMethod === 'pickup' ? $pickupTime : null,
                'phone' => $phone,
                'recipient_name' => $customerName,
                'address_detail' => $addressDetail,
                'postal_code' => $postalCode,
                'order_number' => sprintf('ALBA-%s-%03d', now()->format('Ymd'), 0), // placeholder, updated below
            ]);
            $order->user_id = $user?->id;
            $order->total_price = $totalPrice;
            $order->status = OrderStatus::Pending->value;
            $order->shipping_cost = $shippingCost;
            $order->save();

            // Regenerate order number with real ID
            $order->update([
                'order_number' => sprintf('ALBA-%s-%03d', now()->format('Ymd'), $order->id),
            ]);

            // Create order items (price snapshot from cart, protected field)
            foreach ($cartItems as $cart) {
                $item = new OrderItem;
                $item->fill([
                    'order_id' => $order->id,
                    'product_id' => $cart->product_id,
                    'quantity' => $cart->quantity,
                ]);
                $item->price = $cart->price;
                $item->save();
            }

            // Audit trail
            OrderStatusLog::create([
                'order_id' => $order->id,
                'old_status' => null,
                'new_status' => OrderStatus::Pending->value,
            ]);

            // Clear cart
            $cartItems->each->delete();

            return $order->fresh();
        });
    }
}
