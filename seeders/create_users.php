<?php

declare(strict_types=1);

use Hyperf\Database\Seeders\Seeder;
use App\Model\User;
use Tokio\Hyperf3Hashing\Hash;

class CreateUsers extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 检查是否已存在数据，避免重复填充
        if (User::count() > 0) {
            echo "用户表已存在数据，跳过执行。\n";
            return;
        }

        // 准备待插入的用户数据数组
        $users = [];
        for ($i = 0; $i < 10; $i++) { // 例如，生成10个测试用户
            $users[] = [
                'username' => 'testuser' . $i,
                'password' => Hash::make('password' . $i), // 使用Hash类进行密码加密
                'nickname' => '测试用户' . $i,
                'email' => 'testuser' . $i . '@example.com',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        // 批量插入数据
        User::insert($users);
    }
}
