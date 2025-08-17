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
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('product_quantity', 'shipment_quantity');
            $table->renameColumn('product_description', 'shipment_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('shipment_quantity', 'product_quantity');
            $table->renameColumn('shipment_description', 'product_description');
        });
    }
};
