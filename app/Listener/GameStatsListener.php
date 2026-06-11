<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\AfterWorkerStart;
use Hyperf\Redis\Redis;

#[Listener]
class GameStatsListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            AfterWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        // 记录游戏统计信息
        $redis = make(Redis::class);

        go(function () use ($redis) {
            while (true) {
                $onlinePlayers = $redis->sCard('slot:online_players');
                $activeRooms = count($redis->zRangeByScore('slot:rooms', time() - 3600, time()));

                // 记录到监控系统
                $redis->hSet('slot:stats', 'online_players', $onlinePlayers);
                $redis->hSet('slot:stats', 'active_rooms', $activeRooms);
                $redis->hSet('slot:stats', 'last_update', time());

                sleep(60); // 每分钟更新一次
            }
        });
    }
}
