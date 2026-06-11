<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;

class DataGeneratorService
{
    private int $userCounter = 0;
    private int $orderCounter = 0;

    /**
     * 生成用户数据
     */
    public function generateUser(): array
    {
        $this->userCounter++;

        $promoters = ['promo_001', 'promo_002', 'promo_003', 'promo_004', 'promo_005'];
        $channels = ['channel_001', 'channel_002', 'channel_003', 'channel_004', 'channel_005'];

        $user = [
            'uid' => 'auto_user_' . time() . '_' . $this->userCounter,
            'appId' => 'app_001',
            'promoterId' => $promoters[array_rand($promoters)],
            'channelId' => $channels[array_rand($channels)],
            'registerTime' => date('Y-m-d H:i:s'),
            'bankCard' => 'card_auto_' . $this->userCounter,
            'currCnt' => rand(1, 10),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $user;
    }

    /**
     * 生成订单数据
     */
    public function generateOrder(string $uid): array
    {
        $this->orderCounter++;

        $products = [
            ['id' => 'product_001', 'price' => 30],
            ['id' => 'product_002', 'price' => 50],
            ['id' => 'product_003', 'price' => 100],
            ['id' => 'product_004', 'price' => 150],
            ['id' => 'product_005', 'price' => 200],
        ];

        $product = $products[array_rand($products)];
        $discount = rand(80, 100) / 100;
        $price = round($product['price'] * $discount, 4);

        $statuses = ['pending', 'success', 'failed'];
        $status = $statuses[array_rand($statuses)];

        $order = [
            'providerOrderId' => 'auto_order_' . time() . '_' . $this->orderCounter,
            'appId' => 'app_001',
            'uid' => $uid,
            'channelId' => 'channel_auto',
            'gid' => $product['id'],
            'price' => $price,
            'discount' => $discount,
            'unit' => 'CNY',
            'type' => rand(1, 3),
            'num' => rand(1, 3),
            'createTime' => date('Y-m-d H:i:s'),
            'sendTime' => $status === 'success' ? date('Y-m-d H:i:s', time() + 300) : null,
            'refundTime' => null,
            'status' => $status,
            'try' => $status === 'failed' ? rand(1, 3) : 1,
            'remark' => '自动生成订单',
            'channelFee' => round($price * 0.05, 4),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $order;
    }

    /**
     * 每秒生成数据
     */
    public function generateDataPerSecond(): array
    {
        $results = [
            'users' => 0,
            'orders' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        try {
            // 生成1个用户
            $user = $this->generateUser();
            Db::table('user_table')->insert($user);
            $results['users'] = 1;

            // 为该用户生成1-3个订单
            $orderCount = rand(1, 3);
            for ($i = 0; $i < $orderCount; $i++) {
                $order = $this->generateOrder($user['uid']);
                Db::table('order_table')->insert($order);
                $results['orders']++;
            }

            $results['success'] = true;
            $results['message'] = "生成成功: 1用户, {$results['orders']}订单";

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['message'] = "生成失败: " . $e->getMessage();
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * 批量生成历史数据
     */
    public function generateHistoricalData(int $days = 30, int $usersPerDay = 100): array
    {
        $totalUsers = 0;
        $totalOrders = 0;

        for ($day = 0; $day < $days; $day++) {
            $date = date('Y-m-d', strtotime("-$day days"));

            for ($i = 0; $i < $usersPerDay; $i++) {
                $user = $this->generateUser();
                $user['registerTime'] = $date . ' ' . sprintf('%02d:%02d:%02d', rand(0, 23), rand(0, 59), rand(0, 59));
                $user['created_at'] = $user['registerTime'];
                $user['updated_at'] = $user['registerTime'];

                Db::table('user_table')->insert($user);
                $totalUsers++;

                // 为每个用户生成1-5个订单
                $orderCount = rand(1, 5);
                for ($j = 0; $j < $orderCount; $j++) {
                    $order = $this->generateOrder($user['uid']);
                    $order['createTime'] = date('Y-m-d H:i:s', strtotime($user['registerTime']) + rand(3600, 30*24*3600));
                    $order['created_at'] = $order['createTime'];
                    $order['updated_at'] = $order['createTime'];

                    Db::table('order_table')->insert($order);
                    $totalOrders++;
                }
            }
        }

        return [
            'success' => true,
            'message' => "生成历史数据完成",
            'total_users' => $totalUsers,
            'total_orders' => $totalOrders,
            'days' => $days
        ];
    }
}
