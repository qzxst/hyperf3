<?php

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use App\Event\CrontabProcessed;

#[Listener]
class CrontabProcessedListener implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('log', 'crontab');
    }

    public function listen(): array
    {
        return [
            CrontabProcessed::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof CrontabProcessed) {
            $this->logger->info(sprintf('定时任务[%s]执行完成，耗时%.2f秒', $event->crontab->getName(), $event->startTime));
        }
    }
}
