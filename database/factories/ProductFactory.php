<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Menu asli AlbaSambosa (17 produk).
     * Format: [name, price]
     */
    protected static array $menu = [
        // Frozen Food
        'Sambosa Original Frozen' => ['price' => 42000, 'category' => 'frozen-food'],
        'Sambosa Smoked Beef Frozen' => ['price' => 42000, 'category' => 'frozen-food'],
        'Risol Rougut Frozen' => ['price' => 32000, 'category' => 'frozen-food'],
        'Pastel Ayam Frozen' => ['price' => 35000, 'category' => 'frozen-food'],
        'Roti Maryam Frozen' => ['price' => 28000, 'category' => 'frozen-food'],
        'Kroket Frozen' => ['price' => 45000, 'category' => 'frozen-food'],
        // Makanan Matang
        'Sambosa Original' => ['price' => 5000, 'category' => 'makanan-matang'],
        'Risol Rougut' => ['price' => 5000, 'category' => 'makanan-matang'],
        'Pastel Ayam' => ['price' => 4000, 'category' => 'makanan-matang'],
        'Sambosa Smoked Beef' => ['price' => 5000, 'category' => 'makanan-matang'],
        'Kroket' => ['price' => 5000, 'category' => 'makanan-matang'],
        'Roti Maryam' => ['price' => 5000, 'category' => 'makanan-matang'],
        // Minuman
        'Teh Botol' => ['price' => 6000, 'category' => 'minuman'],
        'Susu Kurma' => ['price' => 10000, 'category' => 'minuman'],
        'Air Mineral' => ['price' => 5000, 'category' => 'minuman'],
        // Tambahan
        'Saus Mentai' => ['price' => 3000, 'category' => 'tambahan'],
        'Saus Sambal' => ['price' => 3000, 'category' => 'tambahan'],
    ];

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(array_keys(self::$menu));
        $item = self::$menu[$name];

        return [
            'category_id' => Category::firstOrCreate(
                ['slug' => $item['category']],
                ['name' => ucwords(str_replace('-', ' ', $item['category'])), 'order' => 99]
            )->id,
            'name' => $name,
            'slug' => Product::uniqueSlug($name),
            'description' => fake()->paragraph(),
            'price' => $item['price'],
            'stock' => fake()->numberBetween(0, 100),
            'stock_reserved' => 0,
            'total_sold' => fake()->numberBetween(0, 200),
            'image' => self::findImage($name),
        ];
    }

    /**
     * Auto-detect product image from storage/app/public/products/.
     * Matches by product slug (lowercase, hyphenated).
     */
    private static function findImage(string $name): ?string
    {
        $slug = Str::slug($name);
        $dir = storage_path('app/public/products');

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
            $path = "{$dir}/{$slug}.{$ext}";
            if (File::exists($path)) {
                return "products/{$slug}.{$ext}";
            }
        }

        return null;
    }
}
