<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\RecommendationService;

class ProductController extends Controller
{
    public function index()
    {
        return view('products.index');
    }

    public function show(Product $product, RecommendationService $recommendation)
    {
        $product->load('category');
        $recommended = $recommendation->get($product, limit: 6);

        return view('products.show', compact('product', 'recommended'));
    }
}
