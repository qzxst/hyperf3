<?php

declare(strict_types=1);

namespace App\Process;

use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;
use App\Service\Game\RoomManager;

#[Process(name: "game-cleanup")]
class GameCleanupProcess extends AbstractProcess
{
    public function handle(): void
    {
        $roomManager = new RoomManager();

        while (true) {
            // 每5分钟清理一次过期房间
            $roomManager->cleanupExpiredRooms();
            sleep(300);
        }
    }
}
