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
        Schema::create('ltv_stats_log', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->comment('统计日期');
            $table->dateTime('start_time')->comment('开始时间');
            $table->dateTime('end_time')->nullable()->comment('结束时间');
            $table->enum('status', ['running', 'success', 'failed'])->default('running')->comment('状态');
            $table->integer('processed_count')->default(0)->comment('处理用户数');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamps();

            // 索引
            $table->index(['stat_date'], 'idx_stat_date');
            $table->index(['status'], 'idx_status');
            $table->index(['start_time'], 'idx_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltv_stats_log');
    }
};
