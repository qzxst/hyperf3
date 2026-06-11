<?php
declare(strict_types=1);

namespace App\Model;

class RoomManager
{
    private array $rooms = [];
    private int $nextRoomId = 1;

    public function createRoom(int $ownerUserId, string $roomName): int
    {
        $roomId = $this->nextRoomId++;
        $this->rooms[$roomId] = [
            'id' => $roomId,
            'name' => $roomName,
            'owner' => $ownerUserId,
            'players' => [], // 存储玩家FD和ID信息
            'max_players' => 4, // 示例容量
            'created_at' => time()
        ];
        return $roomId;
    }

    public function joinRoom(?int $roomId, int $userId, int $fd): bool
    {
        if (!$roomId || !isset($this->rooms[$roomId])) {
            return false;
        }

        $room = &$this->rooms[$roomId];
        if (count($room['players']) >= $room['max_players']) {
            return false;
        }

        // 加入房间
        $room['players'][$fd] = ['user_id' => $userId, 'fd' => $fd];
        return true;
    }

    public function leaveAllRooms(int $fd): void
    {
        foreach ($this->rooms as &$room) {
            if (isset($room['players'][$fd])) {
                unset($room['players'][$fd]);
            }
        }
    }

    public function getAvailableRooms(): array
    {
        return array_filter($this->rooms, function ($room) {
            return count($room['players']) < $room['max_players'];
        });
    }
}
