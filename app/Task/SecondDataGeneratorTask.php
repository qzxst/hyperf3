<?php

declare(strict_types=1);

namespace App\Task;

use Hyperf\Di\Annotation\Inject;
use App\Service\DataGeneratorService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;

class SecondDataGeneratorTask
{
    #[Inject]
    private DataGeneratorService $dataGenerator;

    #[Inject]
    private StdoutLoggerInterface $logger;

    private static int $executionCount = 0;
    private const MAX_EXECUTIONS = 3600; // 最多执行3600次（1小时）

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('log', 'data-generator');
    }
    public function execute()
    {
        self::$executionCount++;

        // 检查是否达到最大执行次数
        if (self::$executionCount > self::MAX_EXECUTIONS) {
            $this->logger->info("达到最大执行次数 " . self::MAX_EXECUTIONS . "，任务停止");
            return;
        }

        try {
            $result = $this->dataGenerator->generateDataPerSecond();

            if ($result['success']) {
                $this->logger->info(sprintf(
                    "[%s] 第 %d 次执行: %s",
                    $result['timestamp'],
                    self::$executionCount,
                    $result['message']
                ));
            } else {
                $this->logger->error(sprintf(
                    "[%s] 执行失败: %s",
                    $result['timestamp'],
                    $result['message']
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error("定时任务执行异常: " . $e->getMessage());
        }
    }
}
