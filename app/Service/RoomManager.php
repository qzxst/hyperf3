<?php

declare(strict_types=1);

namespace App\Service;

class RoomManager
{
    private static $instance = null;
    private $rooms = [];
    private $clientRoomMap = []; // fd -> roomId

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 创建或加入房间
     */
    public function joinRoom(int $fd, string $playerName = null): string
    {
        $roomId = $this->findAvailableRoom();

        // 如果房间不存在，则创建新房间
        if (!isset($this->rooms[$roomId])) {
            $this->rooms[$roomId] = [
                'id' => $roomId,
                'players' => [],
                'status' => 'waiting', // waiting, playing, finished
                'current_turn' => null,
                'created_at' => time()
            ];
        }

        $playerId = (string)$fd;
        $this->rooms[$roomId]['players'][$playerId] = [
            'fd' => $fd,
            'id' => $playerId,
            'name' => $playerName ?: '玩家_' . $fd,
            'hp' => 100,
            'maxHp' => 100,
            'ready' => false
        ];

        $this->clientRoomMap[$fd] = $roomId;

        echo "玩家 {$fd} 加入房间 {$roomId}\n";
        return $roomId;
    }

    /**
     * 寻找可用房间
     */
    private function findAvailableRoom(): string
    {
        foreach ($this->rooms as $roomId => $room) {
            // 如果房间状态为等待且人数小于2，则返回该房间ID
            if ($room['status'] === 'waiting' && count($room['players']) < 2) {
                return $roomId;
            }
        }

        // 创建新房间
        return 'room_' . (count($this->rooms) + 1);
    }

    /**
     * 获取房间信息
     */
    public function getRoom(string $roomId): ?array
    {
        return $this->rooms[$roomId] ?? null;
    }

    /**
     * 获取玩家所在房间
     */
    public function getPlayerRoom(int $fd): ?string
    {
        return $this->clientRoomMap[$fd] ?? null;
    }

    /**
     * 移除玩家
     */
    public function leaveRoom(int $fd): int
    {
        $roomId = $this->getPlayerRoom($fd);
        if (!$roomId) return 0;

        if (isset($this->rooms[$roomId]['players'][(string)$fd])) {
            unset($this->rooms[$roomId]['players'][(string)$fd]);
            echo "玩家 {$fd} 离开房间 {$roomId}\n";
        }

        unset($this->clientRoomMap[$fd]);

        $remainingPlayers = count($this->rooms[$roomId]['players']);

        // 如果房间空了，删除房间
        if ($remainingPlayers === 0) {
            unset($this->rooms[$roomId]);
            echo "房间 {$roomId} 已被删除\n";
        }

        return $remainingPlayers;
    }

    /**
     * 开始游戏
     */
    public function startGame(string $roomId): bool
    {
        if (!isset($this->rooms[$roomId])) return false;

        $room = &$this->rooms[$roomId];
        if (count($room['players']) >= 2) {
            $room['status'] = 'playing';

            // 随机选择先手玩家
            $playerIds = array_keys($room['players']);
            $room['current_turn'] = $playerIds[array_rand($playerIds)];

            // 初始化玩家状态
            foreach ($room['players'] as &$player) {
                $player['hp'] = 100;
                $player['maxHp'] = 100;
                $player['ready'] = true;
            }

            echo "房间 {$roomId} 游戏开始，先手玩家: {$room['current_turn']}\n";
            return true;
        }

        return false;
    }

    private function updateRoomStatus(string $roomId): void
    {
        $room = $this->getRoom($roomId);
        if (!$room) return;

        $alivePlayers = array_filter($room['players'], function ($player) {
            return $player['hp'] > 0;
        });

        if (count($alivePlayers) === 1) {
            $room['status'] = 'finished';
            echo "房间 {$roomId} 游戏结束，胜利者: " . $alivePlayers[0]['name'] . "\n";
        } else {
            $room['status'] = 'playing';
        }
    }

    /**
     * 切换回合
     */
    public function switchTurn(string $roomId): ?string
    {
        if (!isset($this->rooms[$roomId])) return null;

        $room = &$this->rooms[$roomId];
        $playerIds = array_keys($room['players']);

        $currentIndex = array_search($room['current_turn'], $playerIds);
        $nextIndex = ($currentIndex + 1) % count($playerIds);

        $room['current_turn'] = $playerIds[$nextIndex];

        echo "房间 {$roomId} 回合切换: {$playerIds[$currentIndex]} -> {$room['current_turn']}\n";
        return $room['current_turn'];
    }

    /**
     * 执行战斗动作
     */
    public function performAction(string $roomId, string $playerId, string $action, string $targetId): array
    {
        $room = $this->getRoom($roomId);
        if (!$room) return ['success' => false, 'message' => '房间不存在'];

        // 检查是否是当前玩家的回合
        if ($room['current_turn'] !== $playerId) {
            return ['success' => false, 'message' => '不是你的回合'];
        }

        // 执行动作逻辑
        $damage = $this->calculateDamage($action);
        $room['players'][$targetId]['hp'] = max(0, $room['players'][$targetId]['hp'] - $damage);

        $result = [
            'success' => true,
            'damage' => $damage,
            'action' => $action,
            'attacker' => $playerId,
            'target' => $targetId,
            'target_hp' => $room['players'][$targetId]['hp']
        ];

        // 切换回合
        $this->switchTurn($roomId);

        return $result;
    }

    /**
     * 计算伤害
     */
    private function calculateDamage(string $action): int
    {
        $damages = [
            'ATTACK' => rand(10, 20),
            'DEFEND' => rand(5, 10),
            'SKILL' => rand(15, 25)
        ];

        return $damages[$action] ?? 10;
    }

    /**
     * 检查游戏是否结束
     */
    public function checkGameOver(string $roomId): ?string
    {
        $room = $this->getRoom($roomId);
        if (!$room) return null;

        $alivePlayers = array_filter($room['players'], function ($player) {
            return $player['hp'] > 0;
        });

        if (count($alivePlayers) <= 1) {
            $this->rooms[$roomId]['status'] = 'finished';
            return $alivePlayers ? reset($alivePlayers)['id'] : null;
        }

        return null;
    }
}
