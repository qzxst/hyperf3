<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Auth\RegisterRequest;
use Auth\RegisterResponse;
use Auth\LoginRequest;
use Auth\LoginResponse;
use Auth\UserInfo;
use Auth\GetUserInfoRequest;
use Hyperf\DbConnection\Db;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;

class AuthService
{
    public function __construct(
        private JwtService $jwtService,
        private Redis $redis,
        private StdoutLoggerInterface $logger
    ) {}

    /**
     * 用户注册
     */
    public function register(RegisterRequest $request): RegisterResponse
    {
        $response = new RegisterResponse();

        try {
            $username = $request->getUsername();
            $password = $request->getPassword();
            $email = $request->getEmail();
            $nickname = $request->getNickname() ?: $username;

            // 参数验证
            if (empty($username) || empty($password)) {
                $response->setCode(RegisterResponse\ResultCode::INVALID_PARAMS);
                $response->setMessage('用户名和密码不能为空');
                return $response;
            }

            // 检查用户名是否已存在
            if (User::query()->where('username', $username)->exists()) {
                $response->setCode(RegisterResponse\ResultCode::USERNAME_EXISTS);
                $response->setMessage('用户名已存在');
                return $response;
            }

            // 检查邮箱是否已存在
            if ($email && User::query()->where('email', $email)->exists()) {
                $response->setCode(RegisterResponse\ResultCode::EMAIL_EXISTS);
                $response->setMessage('邮箱已被注册');
                return $response;
            }

            // 创建用户
            $user = new User();
            $user->username = $username;
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->email = $email;
            $user->nickname = $nickname;
            $user->level = 1;
            $user->exp = 0;
            $user->coins = 1000; // 新用户赠送金币
            $user->vip_level = 0;
            $user->unlocked_avatars = json_encode([1]); // 默认头像
            $user->status = User::STATUS_ACTIVE;
            $user->register_time = time();
            $user->last_login_time = time();

            $user->save();

            // 构建用户信息
            $userInfo = $this->buildUserInfo($user);

            $response->setCode(RegisterResponse\ResultCode::SUCCESS);
            $response->setMessage('注册成功');
            $response->setUser($userInfo);

            $this->logger->info("User registered: {$username}");
        } catch (\Exception $e) {
            $this->logger->error('Register error: ' . $e->getMessage());
            $response->setCode(RegisterResponse\ResultCode::FAILED);
            $response->setMessage('注册失败');
        }

        return $response;
    }

    /**
     * 用户登录
     */
    public function login(LoginRequest $request): LoginResponse
    {
        $response = new LoginResponse();
        $response->setServerTime(time());

        try {
            $username = $request->getUsername();
            $password = $request->getPassword();

            // 参数验证
            if (empty($username) || empty($password)) {
                $response->setCode(LoginResponse\ResultCode::INVALID_CREDENTIALS);
                $response->setMessage('用户名和密码不能为空');
                return $response;
            }

            // 查找用户
            $user = User::query()->where('username', $username)->first();

            if (!$user) {
                $response->setCode(LoginResponse\ResultCode::INVALID_CREDENTIALS);
                $response->setMessage('用户不存在');
                return $response;
            }

            // 验证密码
            if (!password_verify($password, $user->password)) {
                $response->setCode(LoginResponse\ResultCode::INVALID_CREDENTIALS);
                $response->setMessage('密码错误');
                return $response;
            }

            // 检查用户状态
            if ($user->status !== User::STATUS_ACTIVE) {
                $response->setCode(LoginResponse\ResultCode::USER_LOCKED);
                $response->setMessage('账户已被锁定');
                return $response;
            }

            // 生成 JWT Token
            $token = $this->jwtService->generateToken($user);

            // 更新最后登录时间
            $user->last_login_time = time();
            $user->save();

            // 构建用户信息
            $userInfo = $this->buildUserInfo($user);

            $response->setCode(LoginResponse\ResultCode::SUCCESS);
            $response->setToken($token);
            $response->setUser($userInfo);
            $response->setMessage('登录成功');

            $this->logger->info("User logged in: {$username}");
        } catch (\Exception $e) {
            $this->logger->error('Login error: ' . $e->getMessage());
            $response->setCode(LoginResponse\ResultCode::FAILED);
            $response->setMessage('登录失败');
        }

        return $response;
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(GetUserInfoRequest $request): UserInfo
    {
        $userId = $request->getUserId();

        $user = User::query()->find($userId);
        if (!$user) {
            throw new \Exception('用户不存在');
        }

        return $this->buildUserInfo($user);
    }

    /**
     * 构建用户信息
     */
    private function buildUserInfo(User $user): UserInfo
    {
        $userInfo = new UserInfo();
        $userInfo->setUserId($user->id);
        $userInfo->setUsername($user->username);
        $userInfo->setNickname($user->nickname);
        $userInfo->setEmail($user->email);
        $userInfo->setLevel($user->level);
        $userInfo->setExp($user->exp);
        $userInfo->setCoins($user->coins);
        $userInfo->setVipLevel($user->vip_level);

        $unlockedAvatars = json_decode($user->unlocked_avatars, true) ?: [];
        $userInfo->setUnlockedAvatars($unlockedAvatars);

        $userInfo->setRegisterTime($user->register_time);
        $userInfo->setLastLoginTime($user->last_login_time);

        return $userInfo;
    }
}
