<?php
declare(strict_types=1);

namespace App\Service\Game;

use Hyperf\Redis\Redis;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;

class RoomManager
{
    private Redis $redis;
    private PlayerManager $playerManager;

    private const ROOM_PREFIX = 'slot:room:';
    private const ROOM_LIST_KEY = 'slot:rooms';
    private const ROOM_PLAYERS_PREFIX = 'slot:room_players:';

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
        $this->playerManager = new PlayerManager();
    }

    /**
     * 创建房间
     */
    public function createRoom(string $roomName, int $maxPlayers = 100, int $minBet = 10, int $maxBet = 1000): string
    {
        $roomId = 'room_' . uniqid();
        $roomData = [
            'room_id' => $roomId,
            'room_name' => $roomName,
            'max_players' => $maxPlayers,
            'current_players' => 0,
            'min_bet' => $minBet,
            'max_bet' => $maxBet,
            'status' => 'waiting',
            'created_time' => time(),
            'creator' => 'system'
        ];

        $roomKey = self::ROOM_PREFIX . $roomId;
        $this->redis->hMSet($roomKey, $roomData);

        // 添加到房间列表
        $this->redis->zAdd(self::ROOM_LIST_KEY, time(), $roomId);

        // 创建房间玩家集合
        $playersKey = self::ROOM_PLAYERS_PREFIX . $roomId;
        $this->redis->expire($playersKey, 86400); // 24小时过期

        return $roomId;
    }

    /**
     * 加入房间
     */
    public function joinRoom(string $playerId, string $roomId): bool
    {
        $roomKey = self::ROOM_PREFIX . $roomId;
        $playersKey = self::ROOM_PLAYERS_PREFIX . $roomId;

        // 检查房间是否存在
        if (!$this->redis->exists($roomKey)) {
            return false;
        }

        // 检查房间状态
        $roomStatus = $this->redis->hGet($roomKey, 'status');
        if ($roomStatus !== 'waiting') {
            return false;
        }

        // 检查玩家人数
        $currentPlayers = $this->redis->hGet($roomKey, 'current_players');
        $maxPlayers = $this->redis->hGet($roomKey, 'max_players');

        if ($currentPlayers >= $maxPlayers) {
            return false;
        }

        // 添加玩家到房间
        $this->redis->sAdd($playersKey, $playerId);
        $this->redis->hIncrBy($roomKey, 'current_players', 1);

        // 设置玩家房间信息
        $this->playerManager->setPlayerRoom($playerId, $roomId);

        return true;
    }

    /**
     * 离开房间
     */
    public function leaveRoom(string $playerId, string $roomId): bool
    {
        $roomKey = self::ROOM_PREFIX . $roomId;
        $playersKey = self::ROOM_PLAYERS_PREFIX . $roomId;

        // 从房间移除玩家
        $this->redis->sRem($playersKey, $playerId);
        $this->redis->hIncrBy($roomKey, 'current_players', -1);

        // 移除玩家房间信息
        $this->playerManager->removePlayerRoom($playerId);

        // 如果房间为空，关闭房间
        $currentPlayers = $this->redis->hGet($roomKey, 'current_players');
        if ($currentPlayers <= 0) {
            $this->redis->hSet($roomKey, 'status', 'closed');
        }

        return true;
    }

    /**
     * 获取房间信息
     */
    public function getRoom(string $roomId): ?array
    {
        $roomKey = self::ROOM_PREFIX . $roomId;
        $roomData = $this->redis->hGetAll($roomKey);

        return empty($roomData) ? null : $roomData;
    }

    /**
     * 获取房间内的玩家
     */
    public function getRoomPlayers(string $roomId): array
    {
        $playersKey = self::ROOM_PLAYERS_PREFIX . $roomId;
        $playerIds = $this->redis->sMembers($playersKey);

        $players = [];
        foreach ($playerIds as $playerId) {
            $playerData = $this->playerManager->getPlayer($playerId);
            if ($playerData) {
                $players[] = $playerData;
            }
        }

        return $players;
    }

    /**
     * 获取可用房间列表
     */
    public function getAvailableRooms(int $limit = 10): array
    {
        $roomIds = $this->redis->zRevRange(self::ROOM_LIST_KEY, 0, $limit - 1);

        $rooms = [];
        foreach ($roomIds as $roomId) {
            $roomData = $this->getRoom($roomId);
            if ($roomData && $roomData['status'] === 'waiting') {
                $rooms[] = $roomData;
            }
        }
        if (empty($rooms)) {
            $this->createRoom('Default Room');
            $rooms = $this->getAvailableRooms($limit);
        }

        return $rooms;
    }

    /**
     * 广播消息到房间
     */
    public function broadcastToRoom(string $roomId, callable $messageSender): void
    {
        Coroutine::create(function () use ($roomId, $messageSender) {
            $players = $this->getRoomPlayers($roomId);

            foreach ($players as $player) {
                try {
                    $messageSender($player['player_id']);
                } catch (\Exception $e) {
                    echo "广播消息失败: " . $e->getMessage() . "\n";
                }
            }
        });
    }

    /**
     * 清理过期房间
     */
    public function cleanupExpiredRooms(): void
    {
        Coroutine::create(function () {
            $roomIds = $this->redis->zRangeByScore(self::ROOM_LIST_KEY, 0, time() - 86400); // 24小时前的房间

            foreach ($roomIds as $roomId) {
                $roomData = $this->getRoom($roomId);
                if ($roomData && $roomData['current_players'] == 0) {
                    $this->deleteRoom($roomId);
                }
            }
        });
    }

    /**
     * 删除房间
     */
    private function deleteRoom(string $roomId): void
    {
        $roomKey = self::ROOM_PREFIX . $roomId;
        $playersKey = self::ROOM_PLAYERS_PREFIX . $roomId;

        $this->redis->del($roomKey);
        $this->redis->del($playersKey);
        $this->redis->zRem(self::ROOM_LIST_KEY, $roomId);
    }
}
