<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Swoole\Coroutine;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class DataMonitorCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('data:monitor');
    }

    protected function configure()
    {
        $this->setDescription('数据监控面板')
             ->addOption('duration', 'd', InputOption::VALUE_OPTIONAL, '监控时长(秒)', 300);
    }

    public function handle()
    {
        $duration = (int) $this->input->getOption('duration');
        $endTime = time() + $duration;

        $this->info("📊 数据监控面板 - 持续 {$duration} 秒");
        $this->info("按 Ctrl+C 可提前终止");
        $this->line(str_repeat('=', 60));

        $lastUserCount = Db::table('user_table')->count();
        $lastOrderCount = Db::table('order_table')->count();

        while (time() < $endTime) {
            $currentTime = time();
            $remaining = $endTime - $currentTime;

            // 获取当前数据量
            $currentUserCount = Db::table('user_table')->count();
            $currentOrderCount = Db::table('order_table')->count();

            // 计算增量
            $userIncrement = $currentUserCount - $lastUserCount;
            $orderIncrement = $currentOrderCount - $lastOrderCount;

            $this->clearLine();
            $this->output->write(sprintf(
                "\r🕒 [%s] 📈 用户: %d (+%d) | 订单: %d (+%d) | 剩余: %ds",
                date('H:i:s'),
                $currentUserCount,
                $userIncrement,
                $currentOrderCount,
                $orderIncrement,
                $remaining
            ));

            $lastUserCount = $currentUserCount;
            $lastOrderCount = $currentOrderCount;

            // 等待1秒
            if (extension_loaded('swoole')) {
                Coroutine::sleep(1);
            } else {
                sleep(1);
            }
        }

        $this->info("\n\n🎉 监控结束");
        $this->info("最终统计: 用户 {$currentUserCount}, 订单 {$currentOrderCount}");
    }

    /**
     * 清除当前行
     */
    private function clearLine(): void
    {
        $this->output->write("\033[2K\r");
    }
}
