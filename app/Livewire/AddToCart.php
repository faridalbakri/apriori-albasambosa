<?php

namespace App\Livewire;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Component;

class AddToCart extends Component
{
    public Product $product;

    public int $quantity = 1;

    public function increment(): void
    {
        $this->quantity++;
    }

    public function decrement(): void
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function add(): void
    {
        $available = $this->product->stock - $this->product->stock_reserved;

        if ($available <= 0) {
            $this->dispatch('notify', type: 'error', message: 'Produk sedang habis.');

            return;
        }

        if ($this->quantity > $available) {
            $this->dispatch('notify', type: 'error', message: "Stok hanya tersedia {$available}.");

            return;
        }

        // firstOrCreate prevents duplicate cart entries from concurrent requests.
        // Not fully atomic in MySQL (gap lock), but sufficient for V1 traffic levels.
        // Upgrade to DB::transaction + lockForUpdate if race becomes measurable.
        $attributes = [
            'product_id' => $this->product->id,
            'user_id' => auth()->id(),
            'session_id' => auth()->check() ? null : session()->getId(),
        ];

        $cart = Cart::where($attributes)->first();

        if ($cart) {
            $cart->increment('quantity', $this->quantity);
        } else {
            $cart = new Cart;
            $cart->fill($attributes);
            $cart->quantity = $this->quantity;
            $cart->price = $this->product->price;
            $cart->save();
        }

        $this->dispatch('cart-updated');
        $this->dispatch('notify', type: 'success', message: 'Ditambahkan ke keranjang!');
        $this->quantity = 1;
    }

    public function render(): View
    {
        return view('livewire.add-to-cart');
    }
}
