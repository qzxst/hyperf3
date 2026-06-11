<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\WebSocketServer\Context;
use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use Hyperf\Di\Annotation\Inject;
use App\Model\RoomManager; // 一个假设的房间管理类
use Firebase\JWT\JWT;

class WebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    #[Inject]
    protected Jwt $jwtFactory;

    protected RoomManager $roomManager;

    public function __construct()
    {
        $this->roomManager = new RoomManager();
    }

    public function onOpen($server, $request): void
    {
        // 连接建立，可以在此进行一些初始化操作，但通常登录验证在onMessage中进行
        $server->push($request->fd, json_encode([
            'type' => 'system',
            'message' => 'Connected successfully. Please login.'
        ]));
    }

    public function onMessage($server, $frame): void
    {
        $data = json_decode($frame->data, true);
        if (!$data) {
            $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Invalid JSON']));
            return;
        }

        switch ($data['action'] ?? '') {
            case 'login':
                $this->handleLogin($server, $frame->fd, $data);
                break;
            case 'create_room':
                $this->handleCreateRoom($server, $frame->fd, $data);
                break;
            case 'join_room':
                $this->handleJoinRoom($server, $frame->fd, $data);
                break;
            case 'list_rooms':
                $this->handleListRooms($server, $frame->fd);
                break;
            default:
                $server->push($frame->fd, json_encode(['type' => 'error', 'message' => 'Unknown action']));
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        // 玩家断开连接时，将其从房间中移除
        $this->roomManager->leaveAllRooms($fd);
        echo "Client {$fd} closed\n";
    }

    protected function handleLogin($server, $fd, $data)
    {
        // 验证用户名和密码，这里简化处理
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // 实际项目中，这里应该查询数据库验证用户
        if ($this->validateUser($username, $password)) {
            $payload = ['uid' => $this->getUserIdByUsername($username), 'username' => $username];

            $token = JWT::encode($payload, config('jwt.secret'), config('jwt.algorithm'));

            // 将用户ID和用户名存入连接上下文
            Context::set('user_id', $payload['uid']);
            Context::set('username', $username);

            $server->push($fd, json_encode([
                'type' => 'login_success',
                'token' => $token,
                'user' => $payload
            ]));
        } else {
            $server->push($fd, json_encode(['type' => 'login_failed', 'message' => 'Invalid credentials']));
        }
    }

    protected function handleCreateRoom($server, $fd, $data)
    {
        $userId = Context::get('user_id');
        if (!$userId) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Not authenticated']));
            return;
        }

        $roomId = $this->roomManager->createRoom($userId, $data['room_name'] ?? 'Unnamed Room');
        $server->push($fd, json_encode([
            'type' => 'room_created',
            'room_id' => $roomId,
            'message' => "Room {$roomId} created successfully."
        ]));
    }

    protected function handleJoinRoom($server, $fd, $data)
    {
        $userId = Context::get('user_id');
        if (!$userId) {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Not authenticated']));
            return;
        }

        $roomId = $data['room_id'] ?? null;
        $result = $this->roomManager->joinRoom($roomId, $userId, $fd); // 传入$fd用于后续房间内消息推送

        if ($result) {
            $server->push($fd, json_encode([
                'type' => 'room_joined',
                'room_id' => $roomId,
                'message' => "Joined room {$roomId}."
            ]));
            // 可选：通知房间内其他玩家
        } else {
            $server->push($fd, json_encode(['type' => 'error', 'message' => 'Failed to join room. Room may be full or not exist.']));
        }
    }

    protected function handleListRooms($server, $fd)
    {
        $rooms = $this->roomManager->getAvailableRooms();
        $server->push($fd, json_encode([
            'type' => 'room_list',
            'rooms' => $rooms
        ]));
    }

    // 示例方法，实际项目中需替换为真正的验证逻辑
    protected function validateUser($username, $password): bool
    {
        // 验证逻辑，例如查询数据库
        return !empty($username) && !empty($password);
    }

    protected function getUserIdByUsername($username): int
    {
        // 根据用户名获取用户ID，例如查询数据库
        return 1; // 示例返回值
    }
}
