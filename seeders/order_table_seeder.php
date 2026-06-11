<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use Hyperf\DbConnection\Db;

class OrderTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取所有用户
        $users = Db::table('user_table')->get()->toArray();

        if (empty($users)) {
            return;
        }

        $products = [
            ['id' => 'product_001', 'price' => 30],
            ['id' => 'product_002', 'price' => 50],
            ['id' => 'product_003', 'price' => 100],
            ['id' => 'product_004', 'price' => 150],
            ['id' => 'product_005', 'price' => 200],
            ['id' => 'product_006', 'price' => 25],
            ['id' => 'product_007', 'price' => 75],
            ['id' => 'product_008', 'price' => 120],
            ['id' => 'product_009', 'price' => 180],
            ['id' => 'product_010', 'price' => 250]
        ];

        $statuses = ['pending', 'success', 'failed', 'refunded'];
        $statusWeights = [5, 80, 10, 5]; // 权重

        $orders = [];
        $orderCount = 500;

        for ($i = 1; $i <= $orderCount; $i++) {
            $user = $users[array_rand($users)];
            $product = $products[array_rand($products)];

            $providerOrderId = 'order_' . uniqid();
            $appId = 'app_001';
            $uid = $user->uid;
            $channelId = $user->channelId;
            $gid = $product['id'];
            $basePrice = $product['price'];
            $discount = mt_rand(80, 100) / 100; // 0.8 - 1.0
            $price = round($basePrice * $discount, 4);
            $unit = 'CNY';
            $type = mt_rand(1, 3);
            $num = mt_rand(1, 3);

            // 订单时间在用户注册时间之后
            $registerTime = strtotime($user->registerTime);
            $maxOrderTime = min(time(), $registerTime + 90 * 24 * 60 * 60); // 最多90天内
            $createTime = date('Y-m-d H:i:s', mt_rand($registerTime, $maxOrderTime));

            // 根据权重随机选择状态
            $status = $this->weightedRandom($statuses, $statusWeights);

            $sendTime = null;
            $refundTime = null;
            $try = 1;
            $remark = '测试订单';
            $channelFee = round($price * 0.05, 4); // 渠道费5%

            if ($status === 'success') {
                $sendTime = date('Y-m-d H:i:s', strtotime($createTime) + 300); // 5分钟后发货
            } elseif ($status === 'refunded') {
                $sendTime = date('Y-m-d H:i:s', strtotime($createTime) + 300);
                $refundTime = date('Y-m-d H:i:s', strtotime($createTime) + 24 * 60 * 60); // 1天后退款
            } elseif ($status === 'failed') {
                $try = mt_rand(1, 3);
            }

            $orders[] = [
                'providerOrderId' => $providerOrderId,
                'appId' => $appId,
                'uid' => $uid,
                'channelId' => $channelId,
                'gid' => $gid,
                'price' => $price,
                'discount' => $discount,
                'unit' => $unit,
                'type' => $type,
                'num' => $num,
                'createTime' => $createTime,
                'sendTime' => $sendTime,
                'refundTime' => $refundTime,
                'status' => $status,
                'try' => $try,
                'remark' => $remark,
                'channelFee' => $channelFee,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // 分批插入，避免内存溢出
            if ($i % 100 === 0) {
                Db::table('order_table')->insert($orders);
                $orders = [];
            }
        }

        // 插入剩余数据
        if (!empty($orders)) {
            Db::table('order_table')->insert($orders);
        }
    }

    /**
     * 加权随机选择
     */
    private function weightedRandom(array $items, array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($items as $index => $item) {
            $currentWeight += $weights[$index];
            if ($random <= $currentWeight) {
                return $item;
            }
        }

        return $items[0];
    }
}
