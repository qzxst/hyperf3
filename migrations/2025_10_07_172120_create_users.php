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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->string('nickname', 50);
            $table->string('email', 100)->nullable();
            $table->integer('level')->default(1);
            $table->bigInteger('exp')->default(0);
            $table->bigInteger('coins')->default(0);
            $table->integer('vip_level')->default(0);
            $table->json('unlocked_avatars')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->bigInteger('last_login_time')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamps();

            $table->index('username');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
