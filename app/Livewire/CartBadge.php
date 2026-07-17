<?php

namespace App\Livewire;

use App\Models\Cart;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CartBadge extends Component
{
    public int $count = 0;

    public function mount(): void
    {
        $this->count = $this->getCartCount();
    }

    public function render(): View
    {
        return view('livewire.cart-badge');
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        $this->count = $this->getCartCount();
    }

    private function getCartCount(): int
    {
        return (int) Cart::query()
            ->when(auth()->id(), fn ($q, $id) => $q->where('user_id', $id))
            ->when(! auth()->id(), fn ($q) => $q->where('session_id', session()->getId()))
            ->sum('quantity');
    }
}
