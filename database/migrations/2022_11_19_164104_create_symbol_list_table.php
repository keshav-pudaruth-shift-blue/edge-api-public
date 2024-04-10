<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('symbol_list', function (Blueprint $table) {
            $table->string('name')->unique();
            $table->string('company_name');
            $table->string('exchange');
            $table->boolean('is_index')->default(0);
            $table->boolean('is_enabled')->default(0);
            $table->integer('ib_contract_id')->nullable();
            $table->boolean('has_options')->default(1);
            $table->boolean('sync_options_greeks_enabled')->default(0);
            $table->boolean('sync_options_historical_data_delayed_enabled')->default(0);
            $table->boolean('sync_options_historical_data_live_0_dte_enabled')->default(0);
            $table->unsignedInteger('options_update_interval_default')->default(30);
            $table->unsignedInteger('options_update_strike_range_default')->default(30);
            $table->unsignedInteger('options_update_interval_0_dte')->default(5);
            $table->unsignedInteger('options_update_strike_range_0_dte')->default(20);
            $table->unsignedInteger('options_update_interval_1_dte')->default(30);
            $table->unsignedInteger('options_update_strike_range_1_dte')->default(30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('symbol_list');
    }
};
