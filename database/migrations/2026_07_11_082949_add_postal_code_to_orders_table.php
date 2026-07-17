<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Also adds courier_service to shipments — filename doesn't capture this
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('address_detail');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('courier_service', 50)->nullable()->after('courier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('courier_service');
        });
    }
};
