<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpenAIChatPromptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('openai_chat_prompts', function (Blueprint $table) {
            $table->id()->autoIncrement()->unsigned();
            $table->string('context');
            $table->enum('role', ['system', 'assistant', 'user']);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('openai_chat_prompts');
    }
}
