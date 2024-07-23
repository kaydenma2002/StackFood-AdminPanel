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
        Schema::table('food', function (Blueprint $table) {
            $table->boolean('recommended')->default(0);
            $table->integer('total_order')->unsigned()->default(0);
            $table->integer('maximum_cart_quantity')->nullable();
            $table->float('avg_rating',16, 14)->default(0);
            $table->integer('rating_count')->default(0);
            $table->string('rating',255)->nullable();
            $table->string('slug',255)->nullable();
            $table->integer('item_stock')->default(0);
            $table->integer('sell_count')->default(0);
            $table->string('stock_type',20)->default('unlimited');
            $table->boolean('is_halal')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('food', function (Blueprint $table) {
            $table->dropColumn('recommended');
            $table->dropColumn('total_order');
            $table->dropColumn('maximum_cart_quantity');
            $table->dropColumn('avg_rating');
            $table->dropColumn('rating');
            $table->dropColumn('rating_count');
            $table->dropColumn('slug');
            $table->dropColumn('item_stock');
            $table->dropColumn('sell_count');
            $table->dropColumn('stock_type');
            $table->dropColumn('is_halal');
        });
    }
};
