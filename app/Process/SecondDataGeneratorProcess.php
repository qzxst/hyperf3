<?php

declare(strict_types=1);

namespace App\Process;

use App\Service\DataGeneratorService;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;

#[Process(name: "second-data-generator", nums: 1)]
class SecondDataGeneratorProcess extends AbstractProcess
{
    public function __construct(
        private DataGeneratorService $dataGenerator,
        private StdoutLoggerInterface $logger,
        private LoggerFactory $loggerFactory,
        ContainerInterface  $container,
    ) {
        $this->logger = $loggerFactory->get('log', 'data-generator-process');
        parent::__construct($container);
    }

    public function handle(): void
    {
        $this->logger->info('🚀 每秒数据生成进程启动');

        $generationCount = 0;
        $maxGenerations = 3600; // 最多运行1小时（3600秒）

        while (true) {
            try {
                $result = $this->dataGenerator->generateDataPerSecond();
                $generationCount++;

                if ($result['success']) {
                    $this->logger->info(sprintf(
                        "[%s] 第 %d 秒生成: %s",
                        $result['timestamp'],
                        $generationCount,
                        $result['message']
                    ));
                } else {
                    $this->logger->error(sprintf(
                        "[%s] 生成失败: %s",
                        $result['timestamp'],
                        $result['message']
                    ));
                }

                // 达到最大生成次数后停止
                if ($generationCount >= $maxGenerations) {
                    $this->logger->info("达到最大生成次数 {$maxGenerations}，进程停止");
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->error("数据生成异常: " . $e->getMessage());
            }

            // 等待1秒
            sleep(1);
        }
    }

    /**
     * 进程是否启用
     */
    public function isEnable($server): bool
    {
        // 可以通过环境变量控制是否启用
        return env('ENABLE_SECOND_DATA_GENERATOR', false);
    }
}
