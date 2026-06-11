<?php

declare(strict_types=1);

namespace App\Task;

use App\Event\LtvStatsEvent;
use App\Service\LtvStatsService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\EventDispatcher;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;

// #[Crontab(name: "LTV90dStats", rule: "*/5 * * * *", memo: "LTV 90天统计任务")]
class LtvStatsTask
{
    private StdoutLoggerInterface $logger;
    #[Inject]
    private LtvStatsService $ltvStatsService;

    #[Inject]
    private EventDispatcher $eventDispatcher;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('log', 'crontab');
    }
    public function execute()
    {
        $this->logger->info('开始执行 LTV 统计任务');
        echo "开始执行 LTV 统计调度任务..." . PHP_EOL;

        $processedDays = 0;
        $maxDays = 3; // 每次最多处理3天，避免超时

        // 获取需要统计的日期
        $nextDate = $this->ltvStatsService->getNextStatDate();

        // 处理需要统计的日期
        while ($nextDate && $processedDays < $maxDays && $nextDate <= date('Y-m-d')) {
            echo "开始处理日期: {$nextDate}" . PHP_EOL;

            $result = $this->ltvStatsService->calculateLTVForDate($nextDate);

            // 触发事件
            $event = new LtvStatsEvent($nextDate, $result, 'scheduled');
            $this->eventDispatcher->dispatch($event);

            if ($result['success']) {
                $processedDays++;
                echo "✅ 日期 {$nextDate} 处理完成: " . $result['message'] . PHP_EOL;
            } else {
                echo "❌ 日期 {$nextDate} 处理失败: " . $result['message'] . PHP_EOL;
            }

            // 获取下一个日期
            $nextDate = $this->ltvStatsService->getNextStatDate();
        }
        $this->logger->info('LTV 统计任务执行完成');
        if ($processedDays === 0) {
            echo "没有需要统计的日期" . PHP_EOL;
        } else {
            echo "LTV 调度完成，共处理 {$processedDays} 天数据" . PHP_EOL;
        }
    }
}
