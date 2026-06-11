<?php
declare(strict_types=1);

namespace App\Service\Game;

use Hyperf\Redis\Redis;
use Hyperf\Context\ApplicationContext;

class PlayerManager
{
    private Redis $redis;
    private const PLAYER_PREFIX = 'slot:player:';
    private const ONLINE_PLAYERS_KEY = 'slot:online_players';
    private const PLAYER_ROOM_PREFIX = 'slot:player_room:';

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
    }

    /**
     * 创建或获取玩家
     */
    public function createOrGetPlayer(string $playerId, string $playerName = null): array
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;

        // 检查玩家是否存在
        if (!$this->redis->exists($playerKey)) {
            $playerData = [
                'player_id' => $playerId,
                'player_name' => $playerName ?: '玩家_' . substr($playerId, 0, 8),
                'balance' => 10000, // 初始余额
                'level' => 1,
                'experience' => 0,
                'last_login_time' => time(),
                'created_time' => time(),
            ];

            $this->redis->hMSet($playerKey, $playerData);
        } else {
            // 更新最后登录时间
            $this->redis->hSet($playerKey, 'last_login_time', time());
            $playerData = $this->redis->hGetAll($playerKey);
        }

        // 添加到在线玩家集合
        $this->redis->sAdd(self::ONLINE_PLAYERS_KEY, $playerId);

        return $playerData;
    }

    /**
     * 获取玩家信息
     */
    public function getPlayer(string $playerId): ?array
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;
        $playerData = $this->redis->hGetAll($playerKey);

        return empty($playerData) ? null : $playerData;
    }

    /**
     * 更新玩家余额
     */
    public function updateBalance(string $playerId, int $amount): bool
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;

        return $this->redis->hIncrBy($playerKey, 'balance', $amount) !== false;
    }

    /**
     * 获取玩家余额
     */
    public function getBalance(string $playerId): int
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;

        return (int) $this->redis->hGet($playerKey, 'balance');
    }

    /**
     * 设置玩家所在房间
     */
    public function setPlayerRoom(string $playerId, string $roomId): void
    {
        $key = self::PLAYER_ROOM_PREFIX . $playerId;
        $this->redis->setex($key, 3600, $roomId); // 1小时过期
    }

    /**
     * 获取玩家所在房间
     */
    public function getPlayerRoom(string $playerId): ?string
    {
        $key = self::PLAYER_ROOM_PREFIX . $playerId;

        return $this->redis->get($key) ?: null;
    }

    /**
     * 移除玩家房间信息
     */
    public function removePlayerRoom(string $playerId): void
    {
        $key = self::PLAYER_ROOM_PREFIX . $playerId;
        $this->redis->del($key);
    }

    /**
     * 玩家下线
     */
    public function playerOffline(string $playerId): void
    {
        // 从在线玩家集合移除
        $this->redis->sRem(self::ONLINE_PLAYERS_KEY, $playerId);

        // 移除房间信息
        $this->removePlayerRoom($playerId);
    }

    /**
     * 获取在线玩家数量
     */
    public function getOnlinePlayerCount(): int
    {
        return $this->redis->sCard(self::ONLINE_PLAYERS_KEY);
    }

    /**
     * 获取所有在线玩家
     */
    public function getOnlinePlayers(): array
    {
        return $this->redis->sMembers(self::ONLINE_PLAYERS_KEY);
    }

    /**
     * 添加玩家经验
     */
    public function addExperience(string $playerId, int $exp): void
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;
        $this->redis->hIncrBy($playerKey, 'experience', $exp);

        // 检查升级
        $this->checkLevelUp($playerId);
    }

    /**
     * 检查玩家升级
     */
    private function checkLevelUp(string $playerId): void
    {
        $playerKey = self::PLAYER_PREFIX . $playerId;
        $playerData = $this->redis->hGetAll($playerKey);

        $currentLevel = (int)($playerData['level'] ?? 1);
        $currentExp = (int)($playerData['experience'] ?? 0);

        $requiredExp = $currentLevel * 1000; // 每级需要1000经验

        if ($currentExp >= $requiredExp) {
            $newLevel = $currentLevel + 1;
            $this->redis->hSet($playerKey, 'level', $newLevel);
            $this->redis->hIncrBy($playerKey, 'balance', $newLevel * 1000); // 升级奖励
        }
    }
}
