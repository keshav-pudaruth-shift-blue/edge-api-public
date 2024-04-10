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
    public function up()
    {
        Schema::create('users_social_providers', function (Blueprint $table) {
            $table->id();
            $table->text('provider');
            $table->text('provider_user_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('discord_guilds')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_social_providers');
    }
};
