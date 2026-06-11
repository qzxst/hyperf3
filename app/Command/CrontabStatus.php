<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\LtvStatsLog;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command]
class CrontabStatus extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('crontab:status');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查看定时任务执行状态');
    }

    public function handle()
    {
        $this->line('📊 定时任务执行状态监控', 'info');
        $this->line(str_repeat('=', 80), 'info');

        // 查看 LTV 统计任务的执行情况
        $this->checkLtvStatsStatus();

        $this->line('');

        // 查看系统进程状态
        $this->checkProcessStatus();
    }

    private function checkLtvStatsStatus()
    {
        $this->line('📈 LTV 统计任务执行情况:', 'info');

        try {
            // 今日执行情况 - 修复：使用 start_time 字段
            $todayStats = LtvStatsLog::whereDate('start_time', date('Y-m-d'))
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();

            // 修复：安全地访问数组元素
            $successCount = $todayStats['success'] ?? 0;
            $failedCount = $todayStats['failed'] ?? 0;
            $runningCount = $todayStats['running'] ?? 0;

            $this->line("   今日执行: 成功 {$successCount} 次, 失败 {$failedCount} 次, 运行中 {$runningCount} 次");

            // 最近一次执行
            $lastExecution = LtvStatsLog::orderBy('id', 'desc')->first();
            if ($lastExecution) {
                $statusMap = [
                    'success' => '✅ 成功',
                    'failed' => '❌ 失败',
                    'running' => '🔄 运行中'
                ];

                $statusText = $statusMap[$lastExecution->status] ?? '未知状态';
                $duration = $lastExecution->end_time
                    ? round((strtotime("{$lastExecution->end_time}") - strtotime("{$lastExecution->start_time}")), 2) . '秒'
                    : '运行中';

                $this->line("   最近执行: {$lastExecution->stat_date} - {$statusText} - 耗时: {$duration}");
            } else {
                $this->line("   最近执行: 暂无执行记录", 'comment');
            }

            // 数据完整性检查
            $this->checkDataCompleteness();
        } catch (\Exception $e) {
            $this->error("   获取执行状态失败: " . $e->getMessage());
        }
    }

    private function checkDataCompleteness()
    {
        try {
            $latestStatDate = LtvStatsLog::where('status', 'success')
                ->orderBy('stat_date', 'desc')
                ->value('stat_date');
            if ($latestStatDate) {
                $missingDays = $this->getMissingStatDays("{$latestStatDate}");
                if (!empty($missingDays)) {
                    $this->line("   ⚠️  数据缺失日期: " . implode(', ', array_slice($missingDays, 0, 5)) . (count($missingDays) > 5 ? '...' : ''), 'warning');
                } else {
                    $this->line('   ✅ 数据完整，无缺失日期', 'info');
                }
            } else {
                $this->line('   ℹ️  暂无成功执行的统计记录', 'comment');
            }
        } catch (\Exception $e) {
            $this->error("   检查数据完整性失败: " . $e->getMessage());
        }
    }

    private function checkProcessStatus()
    {
        $this->line('🖥️  系统进程状态:', 'info');

        try {
            // 检查定时任务进程是否运行
            $processCommand = 'ps aux | grep "crontab" | grep -v grep';
            $output = shell_exec($processCommand);

            if ($output && trim($output) !== '') {
                $processCount = substr_count($output, PHP_EOL);
                $this->line("   定时任务进程: ✅ 运行中 ({$processCount} 个进程)", 'info');
            } else {
                $this->line("   定时任务进程: ❌ 未运行", 'error');
            }

            // 检查 Hyperf 服务状态
            $hyperfProcess = 'ps aux | grep "hyperf" | grep -v grep | grep -v "bin/hyperf.php"';
            $hyperfOutput = shell_exec($hyperfProcess);

            if ($hyperfOutput && trim($hyperfOutput) !== '') {
                $hyperfCount = substr_count($hyperfOutput, PHP_EOL);
                $this->line("   Hyperf 服务: ✅ 运行中 ({$hyperfCount} 个进程)", 'info');
            } else {
                $this->line("   Hyperf 服务: ❌ 未运行", 'error');
            }
        } catch (\Exception $e) {
            $this->error("   检查进程状态失败: " . $e->getMessage());
        }
    }

    private function getMissingStatDays(string $latestDate): array
    {
        try {
            $missingDays = [];
            $currentDate = date('Y-m-d');
            $checkDate = date('Y-m-d', strtotime($latestDate . ' +1 day'));

            while ($checkDate <= $currentDate) {
                $exists = LtvStatsLog::where('stat_date', $checkDate)
                    ->where('status', 'success')
                    ->exists();

                if (!$exists) {
                    $missingDays[] = $checkDate;
                }

                $checkDate = date('Y-m-d', strtotime($checkDate . ' +1 day'));
            }

            return $missingDays;
        } catch (\Exception $e) {
            $this->error("   检查缺失日期失败: " . $e->getMessage());
            return [];
        }
    }
}
