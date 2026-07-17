<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Frozen Food', 'Sambal', 'Catering', 'Snack', 'Minuman']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'order' => fake()->numberBetween(0, 10),
        ];
    }
}
