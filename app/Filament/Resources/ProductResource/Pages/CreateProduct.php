<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // price & stock excluded from $fillable.
        // Set via explicit property assignment in admin context (Filament is trusted).
        $product = new Product;

        $product->category_id = $data['category_id'];
        $product->name = $data['name'];
        $product->description = $data['description'] ?? null;
        $product->image = $data['image'] ?? null;
        $product->price = $data['price'] ?? 0;
        $product->stock = (int) ($data['stock'] ?? 0);

        $product->save();

        return $product;
    }
}
