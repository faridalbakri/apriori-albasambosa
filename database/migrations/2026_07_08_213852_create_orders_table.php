<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->decimal('total_price', 12, 2);
            $table->string('status')->default('pending'); // OrderStatus enum
            $table->string('payment_method')->nullable();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->dateTime('pickup_time')->nullable();
            $table->string('phone')->nullable(); // guest phone
            $table->timestamps();

            // N-17: indexing
            $table->index('status');
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
