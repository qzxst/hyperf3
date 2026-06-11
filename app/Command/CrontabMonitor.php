<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\LtvStatsLog;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class CrontabMonitor extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('crontab:monitor');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('实时监控定时任务执行情况')
            ->addOption('duration', null, InputOption::VALUE_OPTIONAL, '监控时长(秒)', 300);
    }

    public function handle()
    {
        $duration = (int) $this->input->getOption('duration');
        $endTime = time() + $duration;

        $this->line('🔍 定时任务实时监控', 'info');
        $this->line("监控时长: {$duration} 秒 (直到 " . date('H:i:s', $endTime) . ")");
        $this->line('按 Ctrl+C 退出监控');
        $this->line(str_repeat('-', 80), 'info');

        $lastLogId = LtvStatsLog::max('id') ?: 0;

        while (time() < $endTime) {
            $newLogs = LtvStatsLog::where('id', '>', $lastLogId)
                ->orderBy('id', 'asc')
                ->get();

            foreach ($newLogs as $log) {
                $this->displayLogEntry($log);
                $lastLogId = $log->id;
            }

            // 显示当前运行中的任务
            $this->displayRunningTasks();

            sleep(5); // 每5秒刷新一次
        }

        $this->line('监控结束', 'info');
    }

    private function displayLogEntry(LtvStatsLog $log): void
    {
        $timestamp = date('H:i:s');
        $statusMap = [
            'success' => ['icon' => '✅', 'color' => 'info'],
            'failed' => ['icon' => '❌', 'color' => 'error'],
            'running' => ['icon' => '🔄', 'color' => 'comment']
        ];

        $status = $statusMap[$log->status];
        $message = "[{$timestamp}] {$status['icon']} 任务执行: {$log->stat_date} - {$log->status}";

        if ($log->status === 'success') {
            $message .= " - 处理 {$log->processed_count} 条记录";
        } elseif ($log->status === 'failed') {
            $message .= " - 错误: " . substr($log->error_message ?: '未知错误', 0, 50);
        }

        $this->line($message, $status['color']);
    }

    private function displayRunningTasks(): void
    {
        $runningTasks = LtvStatsLog::where('status', 'running')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($runningTasks->isNotEmpty()) {
            $this->line('当前运行中的任务:', 'comment');
            foreach ($runningTasks as $task) {
                $runningTime = time() - strtotime($task->start_time);
                $this->line("  • {$task->stat_date} - 已运行 {$runningTime} 秒", 'comment');
            }
        }
    }
}
