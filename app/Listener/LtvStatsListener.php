<?php

declare(strict_types=1);

namespace App\Listener;

use App\Event\LtvStatsEvent;
use Hyperf\Event\Annotation\Listener;
use Psr\Container\ContainerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

#[Listener]
class LtvStatsListener implements ListenerInterface
{

    private LoggerInterface $logger;

    public function __construct(protected ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('ltv_stats');
    }

    public function listen(): array
    {
        return [
            LtvStatsEvent::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$event instanceof LtvStatsEvent) {
            return;
        }

        $message = sprintf(
            'LTV统计-%s: 日期=%s, 成功=%s, 处理记录=%s',
            $event->type,
            $event->statDate,
            $event->result['success'] ? '是' : '否',
            $event->result['processed_count'] ?? 0
        );

        if ($event->result['success']) {
            $this->logger->info($message);
        } else {
            $this->logger->error($message, ['error' => $event->result['error'] ?? '']);
        }
    }
}
