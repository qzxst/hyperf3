<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\LtvStatsLog;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class CrontabLogs extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('crontab:logs');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查看定时任务执行日志')
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, '查看指定日期的日志 (Y-m-d)')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, '显示条数', 10)
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, '按状态过滤: success, failed, running');
    }

    public function handle()
    {
        $date = $this->input->getOption('date');
        $limit = (int) $this->input->getOption('limit');
        $status = $this->input->getOption('status');

        $this->line('📋 定时任务执行日志', 'info');
        $this->line(str_repeat('=', 100), 'info');

        $query = LtvStatsLog::query();

        if ($date) {
            $query->whereDate('created_at', $date);
            $this->line("筛选日期: {$date}");
        }

        if ($status && in_array($status, ['success', 'failed', 'running'])) {
            $query->where('status', $status);
            $this->line("筛选状态: {$status}");
        }

        $logs = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->line('没有找到相关日志记录', 'comment');
            return;
        }

        $rows = [];
        foreach ($logs as $log) {
            $statusIcon = [
                'success' => '✅',
                'failed' => '❌',
                'running' => '🔄'
            ][$log->status];

            $duration = $log->end_time
                ? round((strtotime("{$log->end_time}") - strtotime("{$log->start_time}")), 2) . 's'
                : '运行中';

            $rows[] = [
                'id' => $log->id,
                'stat_date' => $log->stat_date,
                'start_time' => $log->start_time,
                'end_time' => $log->end_time ?: '-',
                'status' => $statusIcon . ' ' . $log->status,
                'duration' => $duration,
                'processed' => $log->processed_count,
                'error' => $log->error_message ? substr($log->error_message, 0, 30) . '...' : '-',
            ];
        }

        $this->table(
            ['ID', '统计日期', '开始时间', '结束时间', '状态', '耗时', '处理记录', '错误信息'],
            $rows
        );

        // 统计信息
        $successCount = $logs->where('status', 'success')->count();
        $failedCount = $logs->where('status', 'failed')->count();
        $runningCount = $logs->where('status', 'running')->count();

        $this->line('');
        $this->line("统计: 成功 {$successCount} 次, 失败 {$failedCount} 次, 运行中 {$runningCount} 次", 'info');
    }
}
