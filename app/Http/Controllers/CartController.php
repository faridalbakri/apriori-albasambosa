<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        return view('cart.index');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $product = Product::findOrFail($request->product_id);
        $available = $product->stock - $product->stock_reserved;

        if ($available <= 0) {
            return response()->json(['message' => 'Produk sedang habis.'], 422);
        }

        $where = [
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'session_id' => auth()->check() ? null : session()->getId(),
        ];

        $cart = Cart::where($where)->first();

        if ($cart) {
            $cart->increment('quantity');
        } else {
            $cart = new Cart;
            $cart->fill([
                'product_id' => $product->id,
                'session_id' => auth()->check() ? null : session()->getId(),
                'quantity' => 1,
            ]);
            if (auth()->check()) {
                $cart->user_id = auth()->id();
            }
            $cart->price = $product->price;
            $cart->save();
        }

        return response()->json([
            'message' => 'Ditambahkan ke keranjang!',
            'count' => $this->cartCount(),
        ]);
    }

    private function cartCount(): int
    {
        return (int) Cart::query()
            ->when(auth()->id(), fn ($q, $id) => $q->where('user_id', $id))
            ->when(! auth()->id(), fn ($q) => $q->where('session_id', session()->getId()))
            ->sum('quantity');
    }
}
