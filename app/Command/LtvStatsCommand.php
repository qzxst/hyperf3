<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\LtvStatsService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\Inject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class LtvStatsCommand extends HyperfCommand
{
    #[Inject]
    private LtvStatsService $ltvStatsService;

    public function __construct()
    {
        parent::__construct('ltv:stats');
    }

    protected function configure()
    {
        $this->setDescription('LTV 90天统计命令')
             ->addArgument('action', InputArgument::OPTIONAL, '操作类型: run, rerun', 'run')
             ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, '统计日期 (Y-m-d)')
             ->addOption('start-date', 's', InputOption::VALUE_OPTIONAL, '开始日期 (Y-m-d)')
             ->addOption('end-date', 'e', InputOption::VALUE_OPTIONAL, '结束日期 (Y-m-d)');
    }

    public function handle()
    {
        $action = $this->input->getArgument('action');

        switch ($action) {
            case 'run':
                $this->runScheduler();
                break;
            case 'rerun':
                $this->rerunStats();
                break;
            default:
                $this->error("未知操作: {$action}");
                $this->info("可用操作: run, rerun");
                break;
        }
    }

    /**
     * 运行调度器
     */
    private function runScheduler()
    {
        $this->info("开始 LTV 调度任务...");

        $processedDays = 0;
        $maxDays = 3;
        $nextDate = $this->ltvStatsService->getNextStatDate();

        if (!$nextDate) {
            $this->info("没有需要统计的日期");
            return;
        }

        while ($nextDate && $processedDays < $maxDays && $nextDate <= date('Y-m-d')) {
            $this->info("开始处理日期: {$nextDate}");

            $result = $this->ltvStatsService->calculateLTVForDate($nextDate);
            if ($result['success']) {
                $processedDays++;
                $this->info("✅ 日期 {$nextDate} 处理完成: " . $result['message']);
            } else {
                $this->error("❌ 日期 {$nextDate} 处理失败: " . $result['message']);
            }

            $nextDate = $this->ltvStatsService->getNextStatDate();
        }

        $this->info("LTV 调度完成，共处理 {$processedDays} 天数据");
    }

    /**
     * 手动重跑统计
     */
    private function rerunStats()
    {
        $date = $this->input->getOption('date');
        $startDate = $this->input->getOption('start-date');
        $endDate = $this->input->getOption('end-date');

        if ($date) {
            $startDate = $date;
            $endDate = $date;
        }

        if (!$startDate || !$endDate) {
            $this->error("请指定日期范围: --start-date 和 --end-date, 或使用 --date 指定单天");
            return;
        }

        if ($startDate > $endDate) {
            $this->error("开始日期不能大于结束日期");
            return;
        }

        $this->info("开始手动重跑: {$startDate} 到 {$endDate}");

        $results = $this->ltvStatsService->rerunDateRange($startDate, $endDate);

        $successCount = 0;
        $failCount = 0;

        foreach ($results as $date => $result) {
            if ($result['success']) {
                $successCount++;
                $this->info("✅ {$date}: " . $result['message']);
            } else {
                $failCount++;
                $this->error("❌ {$date}: " . $result['message']);
            }
        }

        $this->info("重跑完成: 成功 {$successCount} 天, 失败 {$failCount} 天");
    }
}
