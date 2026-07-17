<?php

namespace App\Livewire;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductCatalog extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $sort = 'terlaris';

    public function selectCategory(string $slug): void
    {
        $this->category = $slug;
        $this->search = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->category = '';
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updateQuantity(int $cartId, int $newQty): void
    {
        if ($newQty <= 0) {
            Cart::where('id', $cartId)
                ->where(function ($q) {
                    $q->where('user_id', auth()->id())
                        ->orWhere('session_id', session()->getId());
                })
                ->delete();
            $this->dispatch('cart-updated');
            $this->dispatch('notify', type: 'success', message: 'Item dihapus dari keranjang.');

            return;
        }

        $cart = Cart::where('id', $cartId)
            ->where(function ($q) {
                $q->where('user_id', auth()->id())
                    ->orWhere('session_id', session()->getId());
            })
            ->firstOrFail();

        $available = $cart->product->stock - $cart->product->stock_reserved;

        if ($newQty > $available) {
            $this->dispatch('notify', type: 'error', message: "Stok hanya tersedia {$available}.");

            return;
        }

        $cart->update(['quantity' => $newQty]);
        $this->dispatch('cart-updated');
    }

    public function render(): View
    {
        $categories = Category::orderBy('order')
            ->whereHas('products', fn ($q) => $q->where('stock', '>', 0))
            ->get();

        $cartItems = Cart::with('product')
            ->when(auth()->id(), fn ($q, $id) => $q->where('user_id', $id))
            ->when(! auth()->id(), fn ($q) => $q->where('session_id', session()->getId()))
            ->get()
            ->keyBy('product_id');

        $query = Product::with('category')->where('stock', '>', 0);

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        } elseif ($this->category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $this->category));
        }

        $products = $query
            ->when($this->sort === 'terlaris', fn ($q) => $q->orderByDesc('total_sold'))
            ->when($this->sort === 'terbaru', fn ($q) => $q->orderByDesc('created_at'))
            ->when($this->sort === 'termurah', fn ($q) => $q->orderBy('price'))
            ->paginate(12);

        return view('livewire.product-catalog', compact('categories', 'products', 'cartItems'));
    }
}
