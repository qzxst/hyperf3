<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use App\Model\Room;

class CreateRooms extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 检查是否已存在数据，避免重复填充
        if (Room::count() > 0) {
            echo "房间表已存在数据，跳过执行。\n";
            return;
        }

        // 准备待插入的房间数据数组
        $rooms = [];
        for ($i = 0; $i < 10; $i++) { // 例如，生成10个测试房间
            $rooms[] = [
                'room_name' => '测试房间' . $i,
                'owner_id' => 1, // 假设所有房间的拥有者都是用户ID 1
                'max_players' => 4,
                'current_players' => 0,
                'password' => null, // 无密码
                'status' => Room::STATUS_ACTIVE,
                'game_mode' => '经典模式',
                'created_time' => time(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        // 批量插入数据
        Room::insert($rooms);
    }
}
