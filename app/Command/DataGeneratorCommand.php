<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DataGeneratorService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\StdoutLoggerInterface;
use Swoole\Coroutine;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class DataGeneratorCommand extends HyperfCommand
{
    public function __construct(
        private DataGeneratorService $dataGenerator,
        private StdoutLoggerInterface $logger
    ) {
        parent::__construct('data:generate');
    }

    protected function configure()
    {
        $this->setDescription('数据生成命令')
             ->addOption('mode', 'm', InputOption::VALUE_REQUIRED, '生成模式: second, historical, once', 'once')
             ->addOption('duration', 'd', InputOption::VALUE_OPTIONAL, '持续生成时长(秒)', 60)
             ->addOption('days', null, InputOption::VALUE_OPTIONAL, '历史数据天数', 30)
             ->addOption('users-per-day', null, InputOption::VALUE_OPTIONAL, '每天用户数', 100);
    }

    public function handle()
    {
        $mode = $this->input->getOption('mode');

        switch ($mode) {
            case 'second':
                $this->generatePerSecond();
                break;
            case 'historical':
                $this->generateHistorical();
                break;
            case 'once':
                $this->generateOnce();
                break;
            default:
                $this->error("未知模式: {$mode}");
                $this->info("可用模式: second, historical, once");
                break;
        }
    }

    /**
     * 每秒生成模式
     */
    private function generatePerSecond(): void
    {
        $duration = (int) $this->input->getOption('duration');

        $this->info("🚀 开始每秒数据生成，持续 {$duration} 秒");
        $this->info("按 Ctrl+C 可提前终止");
        $this->line(str_repeat('-', 50));

        $startTime = time();
        $generationCount = 0;

        while (true) {
            $currentDuration = time() - $startTime;

            if ($currentDuration >= $duration) {
                $this->info("⏰ 达到指定时长 {$duration} 秒，生成结束");
                break;
            }

            try {
                $result = $this->dataGenerator->generateDataPerSecond();
                $generationCount++;

                if ($result['success']) {
                    $this->info(sprintf(
                        "[%d/%d] %s - %s",
                        $currentDuration,
                        $duration,
                        $result['timestamp'],
                        $result['message']
                    ));
                } else {
                    $this->error(sprintf(
                        "[%d/%d] 生成失败: %s",
                        $currentDuration,
                        $duration,
                        $result['message']
                    ));
                }
            } catch (\Throwable $e) {
                $this->error("生成异常: " . $e->getMessage());
            }

            // 等待1秒
            if (extension_loaded('swoole')) {
                Coroutine::sleep(1);
            } else {
                sleep(1);
            }
        }

        $this->info("🎉 生成完成！共执行 {$generationCount} 次");
    }

    /**
     * 生成历史数据
     */
    private function generateHistorical(): void
    {
        $days = (int) $this->input->getOption('days');
        $usersPerDay = (int) $this->input->getOption('users-per-day');

        $this->info("📚 开始生成历史数据...");
        $this->info("天数: {$days}, 每天用户数: {$usersPerDay}");

        $result = $this->dataGenerator->generateHistoricalData($days, $usersPerDay);

        if ($result['success']) {
            $this->info("✅ " . $result['message']);
            $this->info("📊 统计: {$result['total_users']} 用户, {$result['total_orders']} 订单");
        } else {
            $this->error("❌ 生成失败: " . ($result['message'] ?? '未知错误'));
        }
    }

    /**
     * 单次生成
     */
    private function generateOnce(): void
    {
        $this->info("🔄 单次数据生成...");

        $result = $this->dataGenerator->generateDataPerSecond();

        if ($result['success']) {
            $this->info("✅ " . $result['message']);
        } else {
            $this->error("❌ 生成失败: " . $result['message']);
        }
    }
}
