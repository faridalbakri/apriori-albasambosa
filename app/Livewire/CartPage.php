<?php

namespace App\Livewire;

use App\Models\Cart;
use App\Services\RecommendationService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class CartPage extends Component
{
    public function getCartItemsProperty(): Collection
    {
        return Cart::with('product.category')
            ->when(auth()->id(), fn ($q, $id) => $q->where('user_id', $id))
            ->when(! auth()->id(), fn ($q) => $q->where('session_id', session()->getId()))
            ->get();
    }

    public function getTotalProperty(): float
    {
        return $this->cartItems->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getRecommendationsProperty(): Collection
    {
        return app(RecommendationService::class)->get(context: null, limit: 4);
    }

    public function updateQuantity(int $cartId, int $newQty): void
    {
        if ($newQty <= 0) {
            $this->remove($cartId);

            return;
        }

        $cart = $this->findOwnedCart($cartId);
        $available = $cart->product->stock - $cart->product->stock_reserved;

        if ($newQty > $available) {
            $this->dispatch('notify', type: 'error', message: "Stok hanya tersedia {$available}.");

            return;
        }

        $cart->update(['quantity' => $newQty]);
        $this->dispatch('cart-updated');
    }

    public function remove(int $cartId): void
    {
        $this->findOwnedCart($cartId)->delete();
        $this->dispatch('cart-updated');
        $this->dispatch('notify', type: 'success', message: 'Item dihapus dari keranjang.');
    }

    private function findOwnedCart(int $cartId): Cart
    {
        return Cart::where('id', $cartId)
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                    ->orWhere('session_id', session()->getId());
            })
            ->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.cart-page', [
            'cartItems' => $this->cartItems,
            'total' => $this->total,
            'recommendations' => $this->recommendations,
        ]);
    }
}
