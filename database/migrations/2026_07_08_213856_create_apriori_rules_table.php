<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apriori_rules', function (Blueprint $table) {
            $table->id();
            $table->json('antecedent');
            $table->json('consequent');
            $table->float('support');
            $table->float('confidence');
            $table->float('lift');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apriori_rules');
    }
};
