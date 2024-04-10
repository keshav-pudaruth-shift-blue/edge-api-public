<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTwitterFollowingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('twitter_following', function (Blueprint $table) {
            $table->id()->autoIncrement()->unsigned();
            $table->string('username');
            $table->integer('interval')->default(5);
            $table->dateTime('last_tweet_datetime')->nullable();
            $table->boolean('is_active')->default(1);
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
        Schema::dropIfExists('twitter_following');
    }
}
