<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\LtvStatsLog;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Parser;

#[Command]
class CrontabHealth extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('crontab:health');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('定时任务健康检查');
    }

    public function handle()
    {
        $this->line('🏥 定时任务健康检查', 'info');
        $this->line(str_repeat('=', 60), 'info');

        $checks = [
            '数据库连接' => fn() => $this->checkDatabase(),
            '定时任务配置' => fn() => $this->checkCrontabConfig(),
            '最近执行状态' => fn() => $this->checkRecentExecutions(),
            '进程状态' => fn() => $this->checkProcessHealth(),
        ];

        $passed = 0;
        $failed = 0;

        foreach ($checks as $name => $check) {
            $this->line("检查: {$name}...", 'info');

            try {
                $result = $check();
                if ($result) {
                    $this->line("  ✅ {$name}: 正常", 'info');
                    $passed++;
                } else {
                    $this->line("  ❌ {$name}: 异常", 'error');
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->line("  ❌ {$name}: 检查失败 - " . $e->getMessage(), 'error');
                $failed++;
            }

            $this->line('');
        }

        $this->line(str_repeat('=', 60), 'info');

        if ($failed === 0) {
            $this->line("🎉 所有检查通过! 定时任务系统健康", 'info');
        } else {
            $this->line("⚠️  检查结果: {$passed} 项通过, {$failed} 项失败", 'warning');
        }
    }

    private function checkDatabase(): bool
    {
        try {
            // 简单的数据库查询测试
            $count = LtvStatsLog::count();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCrontabConfig(): bool
    {
        try {
            $config = config('crontab');
            if (empty($config) || !isset($config['crontab'])) {
                return false;
            }

            $crontabs = $config['crontab'];
            return !empty($crontabs);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRecentExecutions(): bool
    {
        try {
            // 检查最近1小时是否有执行记录
            $recent = LtvStatsLog::where('start_time', '>=', date('Y-m-d H:i:s', strtotime('-1 hour')))
                ->exists();

            return $recent;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkProcessHealth(): bool
    {
        try {
            $output = shell_exec('ps aux | grep "hyperf" | grep "crontab" | grep -v grep');
            return !empty(trim($output ?? ''));
        } catch (\Exception $e) {
            return false;
        }
    }
}
