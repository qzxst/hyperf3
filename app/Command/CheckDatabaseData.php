<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

#[Command]
class CheckDatabaseData extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('check:database-data');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('检查数据库数据生成情况');
    }

    public function handle()
    {
        $this->line('开始检查数据库数据...', 'info');

        // 检查用户表
        $userCount = Db::table('user_table')->count();
        $this->line("用户表记录数: {$userCount}", $userCount > 0 ? 'info' : 'error');

        // 检查订单表
        $orderCount = Db::table('order_table')->count();
        $this->line("订单表记录数: {$orderCount}", $orderCount > 0 ? 'info' : 'error');

        // 检查订单状态分布
        $orderStatus = Db::table('order_table')
            ->select('status', Db::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->toArray();

        $this->line("订单状态分布:", 'info');
        foreach ($orderStatus as $status) {
            $this->line("  {$status->status}: {$status->count}", 'info');
        }

        // 检查用户注册时间范围
        $userTimeRange = Db::table('user_table')
            ->select(
                Db::raw('MIN(registerTime) as min_time'),
                Db::raw('MAX(registerTime) as max_time')
            )
            ->first();

        $this->line("用户注册时间范围:", 'info');
        $this->line("  最早: {$userTimeRange->min_time}", 'info');
        $this->line("  最晚: {$userTimeRange->max_time}", 'info');

        $this->line('数据库数据检查完成!', 'info');
    }
}
