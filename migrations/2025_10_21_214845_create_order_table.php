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
        Schema::create('order_table', function (Blueprint $table) {
            $table->string('providerOrderId', 100)->primary()->comment('供应商订单ID');
            $table->string('appId', 50)->comment('应用ID');
            $table->string('uid', 50)->comment('用户ID');
            $table->string('channelId', 50)->nullable()->comment('渠道ID');
            $table->string('gid', 50)->comment('商品ID');
            $table->decimal('price', 18, 4)->comment('价格');
            $table->decimal('discount', 5, 4)->default(1.0)->comment('折扣');
            $table->string('unit', 20)->default('CNY')->comment('货币单位');
            $table->integer('type')->default(1)->comment('订单类型');
            $table->integer('num')->default(1)->comment('数量');
            $table->dateTime('createTime')->comment('创建时间');
            $table->dateTime('sendTime')->nullable()->comment('发货时间');
            $table->dateTime('refundTime')->nullable()->comment('退款时间');
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending')->comment('订单状态');
            $table->integer('try')->default(0)->comment('尝试次数');
            $table->text('remark')->nullable()->comment('备注');
            $table->json('extInfo')->nullable()->comment('扩展信息');
            $table->decimal('channelFee', 18, 4)->default(0)->comment('渠道费用');
            $table->timestamps();

            // 索引
            $table->index(['uid'], 'idx_uid');
            $table->index(['createTime'], 'idx_create_time');
            $table->index(['status'], 'idx_status');
            $table->index(['appId'], 'idx_app_id');
            $table->index(['gid'], 'idx_gid');
            $table->index(['uid', 'createTime'], 'idx_uid_create_time');
            $table->index(['createTime', 'status'], 'idx_create_time_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_table');
    }
};
