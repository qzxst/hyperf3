<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('room_name', 100);
            $table->bigInteger('owner_id');
            $table->integer('max_players')->default(4);
            $table->integer('current_players')->default(0);
            $table->string('password', 255)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('game_mode', 50)->default('classic');
            $table->bigInteger('created_time');
            $table->timestamps();

            $table->index('owner_id');
            $table->index('status');
            $table->index('game_mode');
            $table->index('created_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
