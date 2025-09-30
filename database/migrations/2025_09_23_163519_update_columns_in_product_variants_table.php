<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Drop old price column
            $table->dropColumn('price');

            // Add new columns
            $table->decimal('selling_price', 10, 2)->after('id');
            $table->decimal('product_cost', 10, 2)->after('selling_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['selling_price', 'product_cost']);

            // Restore old price column
            $table->decimal('price', 10, 2)->after('id');
        });
    }
};
