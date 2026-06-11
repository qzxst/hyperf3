<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Auth\VerifyTokenRequest;
use Auth\VerifyTokenResponse;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Hyperf\Contract\StdoutLoggerInterface;

class JwtService
{
    private string $secretKey;
    private string $algorithm = 'HS256';

    public function __construct(private StdoutLoggerInterface $logger)
    {
        $this->secretKey = env('JWT_SECRET', 'your-jwt-secret-key');
    }

    /**
     * 生成 JWT Token
     */
    public function generateToken(User $user): string
    {
        $payload = [
            'user_id' => $user->id,
            'username' => $user->username,
            'iat' => time(),
            'exp' => time() + 86400 * 7, // 7天过期
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    /**
     * 验证 Token
     */
    public function verifyToken(VerifyTokenRequest $request): VerifyTokenResponse
    {
        $response = new VerifyTokenResponse();

        try {
            $token = $request->getToken();

            if (empty($token)) {
                $response->setValid(false);
                $response->setMessage('Token 不能为空');
                return $response;
            }

            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $payload = (array)$decoded;

            // 检查 Token 是否过期
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $response->setValid(false);
                $response->setMessage('Token 已过期');
                return $response;
            }

            // 获取用户信息
            $user = User::query()->find($payload['user_id']);
            if (!$user) {
                $response->setValid(false);
                $response->setMessage('用户不存在');
                return $response;
            }

            $userInfo = (new AuthService($this, $this->logger))->buildUserInfo($user);

            $response->setValid(true);
            $response->setUser($userInfo);
            $response->setMessage('Token 验证成功');

        } catch (\Exception $e) {
            $this->logger->warning('Token verification failed: ' . $e->getMessage());
            $response->setValid(false);
            $response->setMessage('Token 验证失败: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * 从 Token 中获取用户ID
     */
    public function getUserIdFromToken(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            $payload = (array)$decoded;
            return $payload['user_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
