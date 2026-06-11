<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [];
        $promoters = ['promo_001', 'promo_002', 'promo_003', 'promo_004', 'promo_005'];
        $channels = ['channel_001', 'channel_002', 'channel_003', 'channel_004', 'channel_005'];

        $startDate = strtotime('2024-01-01');
        $endDate = strtotime('2025-10-20');

        for ($i = 1; $i <= 100; $i++) {
            $uid = 'user_' . str_pad(strval($i), 3, '0', STR_PAD_LEFT);
            $appId = 'app_001';
            $promoterId = $promoters[array_rand($promoters)];
            $channelId = $channels[array_rand($channels)];
            $registerTime = date('Y-m-d H:i:s', mt_rand($startDate, $endDate));
            $bankCard = 'card_' . str_pad(strval($i), 3, '0', STR_PAD_LEFT);
            $currCnt = mt_rand(1, 10);

            $users[] = [
                'uid' => $uid,
                'appId' => $appId,
                'promoterId' => $promoterId,
                'channelId' => $channelId,
                'registerTime' => $registerTime,
                'bankCard' => $bankCard,
                'currCnt' => $currCnt,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // 分批插入，避免内存溢出
            if ($i % 50 === 0) {
                Db::table('user_table')->insert($users);
                $users = [];
            }
        }

        // 插入剩余数据
        if (!empty($users)) {
            Db::table('user_table')->insert($users);
        }
    }
}
