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
        Schema::create('options_live_data', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('type');
            $table->float('strike');
            $table->date('expiry_date');
            $table->float('delta');
            $table->float('gamma');
            $table->float('theta');
            $table->float('vega');
            $table->float('rho');
            $table->float('vanna');
            $table->float('charm');
            $table->float('underlying_price');
            $table->integer('open_interest')->default(0);
            $table->float('implied_volatility')->default(0.0);
            $table->integer('bearish_volume');
            $table->integer('bullish_volume');
            $table->integer('current_volume');
            $table->dateTime('last_updated');
            $table->timestamps();
            $table->unique(['symbol', 'expiry_date', 'strike', 'type'],'live_data_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('options_live_data');
    }
};
