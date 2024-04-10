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
        Schema::create('options_contracts_greeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('options_contracts_id');
            $table->float('delta');
            $table->float('gamma');
            $table->float('theta');
            $table->float('vega');
            $table->float('rho');
            $table->float('vanna');
            $table->float('charm');
            $table->float('underlying_price');
            $table->float('total_delta');
            $table->float('total_gamma');
            $table->integer('open_interest')->default(0);
            $table->float('implied_volatility')->default(0.0);
            $table->integer('volume');
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
        Schema::dropIfExists('options_contracts_greeks');
    }
};
