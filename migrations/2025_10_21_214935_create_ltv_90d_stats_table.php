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
        Schema::create('ltv_90d_stats', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->comment('统计日期');
            $table->date('register_date')->comment('注册日期');
            $table->string('promoter_id', 50)->nullable()->comment('推广员ID');
            $table->string('channel_id', 50)->nullable()->comment('渠道ID');
            $table->integer('register_count')->default(0)->comment('注册用户数');
            $table->decimal('day1_ltv', 18, 4)->default(0)->comment('1日LTV');
            $table->decimal('day3_ltv', 18, 4)->default(0)->comment('3日LTV');
            $table->decimal('day7_ltv', 18, 4)->default(0)->comment('7日LTV');
            $table->decimal('day30_ltv', 18, 4)->default(0)->comment('30日LTV');
            $table->decimal('day60_ltv', 18, 4)->default(0)->comment('60日LTV');
            $table->decimal('day90_ltv', 18, 4)->default(0)->comment('90日LTV');
            $table->decimal('total_revenue', 18, 4)->default(0)->comment('累计收入');
            $table->decimal('avg_ltv', 18, 4)->default(0)->comment('平均LTV');
            $table->timestamps();

            // 复合唯一索引
            $table->unique(['stat_date', 'register_date', 'promoter_id', 'channel_id'], 'unq_stat_register_promoter_channel');

            // 单字段索引
            $table->index(['register_date'], 'idx_register_date');
            $table->index(['promoter_id'], 'idx_promoter_id');
            $table->index(['channel_id'], 'idx_channel_id');
            $table->index(['stat_date'], 'idx_stat_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ltv_90d_stats');
    }
};
