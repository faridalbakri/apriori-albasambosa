<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // price & stock excluded from $fillable.
        // Set via direct property assignment on the existing record, then strip
        // from mass-assignment data. Only runs when fields are present in form.
        if (array_key_exists('price', $data)) {
            $this->record->price = $data['price'];
        }
        if (array_key_exists('stock', $data)) {
            $this->record->stock = (int) $data['stock'];
        }

        unset($data['price'], $data['stock']);

        return $data;
    }
}
