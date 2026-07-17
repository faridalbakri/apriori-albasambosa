<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add nullable column
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // Step 2: generate slugs for existing products (skip empty names)
        DB::table('products')->orderBy('id')->each(function ($product) {
            $name = trim($product->name);
            if ($name === '') {
                $name = 'product-'.$product->id;
            }

            $slug = Str::slug($name);
            $original = $slug;
            $counter = 1;

            while (DB::table('products')->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $original.'-'.$counter++;
            }

            DB::table('products')->where('id', $product->id)->update(['slug' => $slug]);
        });

        // Step 3: add unique constraint
        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
