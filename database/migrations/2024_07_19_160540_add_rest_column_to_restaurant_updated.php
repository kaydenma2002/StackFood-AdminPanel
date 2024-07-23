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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('schedule_order')->default(0);
            $table->boolean('food_section')->default(1);
            $table->text('additional_data')->nullable();
            $table->text('additional_documents')->nullable();
            $table->string('free_delivery_distance')->nullable();
            $table->boolean('order_subscription_active')->default(0)->nullable();
            $table->string('campaign_status',10)->default('pending')->nullable();
            $table->string('restaurant_model', 50)->default('commission')->nullable();
            $table->double('maximum_shipping_charge', 23, 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('schedule_order');
            $table->dropColumn('food_section');
            $table->dropColumn('additional_data');
            $table->dropColumn('additional_documents');
            $table->dropColumn('free_delivery_distance');
            $table->dropColumn('order_subscription_active');
            $table->dropColumn('campaign_status');
            $table->dropColumn('restaurant_model');
            $table->dropColumn('maximum_shipping_charge');
        });
    }
};
