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
        Schema::create('options_contracts_with_trading_hours', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->bigInteger('options_contracts_id');
            $table->bigInteger('options_contracts_trading_hours_id');
            $table->timestamps();

            $table->unique(['options_contracts_id', 'options_contracts_trading_hours_id'], 'options_contracts_trading_hours_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('options_contracts_with_trading_hours');
    }
};
