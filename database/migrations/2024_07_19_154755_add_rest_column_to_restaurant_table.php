<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->boolean('delivery')->default(1);
            $table->boolean('take_away')->default(1);


            $table->string('rating')->nullable();
            $table->boolean('cutlery')->default(0);
            $table->string('meta_title',100)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_image',100)->nullable();
            $table->decimal('tax', $precision = 6, $scale = 2)->default(0);
            $table->decimal('comission', $precision = 6, $scale = 2)->nullable()->change();
            $table->string('slug',255)->nullable();
            $table->text('qr_code')->nullable();
            $table->boolean('active')->default(1);
            $table->string('off_day')->default(' ');
            $table->string('gst')->nullable();
            $table->boolean('veg')->default(true);
            $table->boolean('non_veg')->default(true);
            $table->foreignId('cuisine_id')->nullable();
            $table->boolean('announcement')->default(0);
            $table->string('announcement_message')->nullable();
            $table->boolean('self_delivery_system')->default(0);
            $table->boolean('pos_system')->default(0);

            $table->string('delivery_time', 10)->nullable()->default('30-40');
            $table->boolean('reviews_section')->default(1);
            $table->dropColumn('currency');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('delivery');
            $table->dropColumn('take_away');


            $table->dropColumn('rating');
            $table->dropColumn('cutlery');
            $table->dropColumn('meta_title');
            $table->dropColumn('meta_description');
            $table->dropColumn('meta_image');
            $table->dropColumn('tax');
            $table->decimal('comission', $precision = 6, $scale = 2)->default(0)->change();
            $table->dropColumn('slug');
            $table->dropColumn('qr_code');
            $table->dropColumn('active');
            $table->dropColumn('off_day');
            $table->dropColumn('gst');
            $table->dropColumn('veg');
            $table->dropColumn('non_veg');
            $table->dropColumn('cuisine_id');
            $table->dropColumn('announcement');
            $table->dropColumn('announcement_message');
            $table->dropColumn('self_delivery_system');

            $table->dropColumn('pos_system');
            $table->dropColumn('delivery_time');
            $table->string('currency')->default('BDT');
            $table->dropColumn('reviews_section');
        });
    }
};
