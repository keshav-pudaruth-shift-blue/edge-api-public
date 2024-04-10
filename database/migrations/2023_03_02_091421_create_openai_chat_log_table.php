<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpenAIChatLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('openai_chat_log', function (Blueprint $table) {
            $table->id()->autoIncrement()->unsigned();
            $table->json('request');
            $table->json('response')->nullable();
            $table->string('context');
            $table->integer('total_tokens')->default(0);
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
        Schema::dropIfExists('openai_chat_log');
    }
}
