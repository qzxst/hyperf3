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
        Schema::create('user_table', function (Blueprint $table) {
            $table->string('uid', 50)->primary()->comment('用户ID');
            $table->string('appId', 50)->comment('应用ID');
            $table->string('promoterId', 50)->nullable()->comment('推广员ID');
            $table->string('channelId', 50)->nullable()->comment('渠道ID');
            $table->dateTime('registerTime')->comment('注册时间');
            $table->string('bankCard', 100)->nullable()->comment('银行卡信息');
            $table->integer('currCnt')->default(0)->comment('当前计数');
            $table->timestamps();

            // 索引
            $table->index(['registerTime'], 'idx_register_time');
            $table->index(['promoterId'], 'idx_promoter_id');
            $table->index(['channelId'], 'idx_channel_id');
            $table->index(['appId'], 'idx_app_id');
            $table->index(['promoterId', 'channelId'], 'idx_promoter_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_table');
    }
};
