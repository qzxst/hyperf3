<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnOpenInterface;
use Swoole\WebSocket\Frame;
use App\Utils\MsgPackHelper;
use App\Service\Game\PlayerManager;
use App\Service\Game\RoomManager;
use App\Service\Game\SlotGameEngine;
use Hyperf\Engine\WebSocket\WebSocket;
use Hyperf\Server\SwooleServerFactory;
use Slot\ClientMessage;
use Slot\ServerMessage;
use Slot\Player;
use Slot\Room;
use Slot\SpinResult;
use Slot\WinLine;

class SlotGameController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    private PlayerManager $playerManager;
    private RoomManager $roomManager;
    private SlotGameEngine $gameEngine;

    // 客户端连接映射
    private array $clientPlayers = [];

    public function __construct()
    {
        $this->playerManager = new PlayerManager();
        $this->roomManager = new RoomManager();
        $this->gameEngine = new SlotGameEngine();
    }

    public function onOpen($server, $request): void
    {
        $fd = $request->fd;
        echo "🎮 客户端 {$fd} 连接成功\n";

        // 生成玩家ID (实际项目中应该从认证获取)
        $playerId = 'player_' . $fd;
        $this->clientPlayers[$fd] = $playerId;

        // 创建或获取玩家
        $playerData = $this->playerManager->createOrGetPlayer($playerId);
        echo "🎮 玩家 {$playerId} 创建或获取成功\n";
        echo "🎮 玩家" . json_encode($playerData) . "\n";

        // 发送玩家信息和房间列表
        $this->sendPlayerInfo($server, $fd, $playerData);
        $this->sendRoomList($server, $fd);
    }

    public function onMessage($server, $frame): void
    {
        $fd = $frame->fd;
        $playerId = $this->clientPlayers[$fd] ?? null;

        if (!$playerId) {
            $this->sendError($server, $fd, '玩家未认证');
            return;
        }

        try {
            // 使用 MsgPack 解码消息
            $clientMsg = MsgPackHelper::decodeProtoMessage($frame->data, ClientMessage::class);

            echo "📨 收到消息类型: " . $clientMsg->getType() . "\n";

            switch ($clientMsg->getType()) {
                case 'JOIN_ROOM':
                    $this->handleJoinRoom($server, $fd, $playerId, $clientMsg->getRoomId());
                    break;

                case 'LEAVE_ROOM':
                    $this->handleLeaveRoom($server, $fd, $playerId, $clientMsg->getRoomId());
                    break;

                case 'SPIN':
                    $this->handleSpin($server, $fd, $playerId, $clientMsg->getBetAmount());
                    break;

                case 'BUY_BONUS':
                    $this->handleBuyBonus($server, $fd, $playerId, $clientMsg->getBonusType());
                    break;

                default:
                    $this->sendError($server, $fd, '未知消息类型: ' . $clientMsg->getType());
            }
        } catch (\Exception $e) {
            echo "❌ 处理消息时出错: " . $e->getMessage() . "\n";
            $this->sendError($server, $fd, $e->getMessage());
        }
    }

    public function onClose($server, $fd, $reactorId): void
    {
        $playerId = $this->clientPlayers[$fd] ?? null;

        if ($playerId) {
            // 玩家下线处理
            $this->playerManager->playerOffline($playerId);

            // 离开房间
            $roomId = $this->playerManager->getPlayerRoom($playerId);
            if ($roomId) {
                $this->roomManager->leaveRoom($playerId, $roomId);

                // 广播房间更新
                $this->broadcastRoomUpdate($server, $roomId);
            }

            unset($this->clientPlayers[$fd]);
        }

        echo "👋 客户端 {$fd} 断开连接\n";
    }

    /**
     * 处理加入房间
     */
    private function handleJoinRoom($server, $fd, string $playerId, string $roomId): void
    {
        if ($this->roomManager->joinRoom($playerId, $roomId)) {
            $room = $this->roomManager->getRoom($roomId);
            $player = $this->playerManager->getPlayer($playerId);

            // 发送成功响应
            $this->sendRoomJoined($server, $fd, $room, $player);

            // 广播房间更新
            $this->broadcastRoomUpdate($server, $roomId);
        } else {
            $this->sendError($server, $fd, '加入房间失败');
        }
    }

    /**
     * 处理离开房间
     */
    private function handleLeaveRoom($server, $fd, string $playerId, string $roomId): void
    {
        if ($this->roomManager->leaveRoom($playerId, $roomId)) {
            $this->sendSuccess($server, $fd, '已离开房间');
            $this->broadcastRoomUpdate($server, $roomId);
        } else {
            $this->sendError($server, $fd, '离开房间失败');
        }
    }

    /**
     * 处理旋转
     */
    private function handleSpin($server, $fd, string $playerId, int $betAmount): void
    {
        try {
            $spinResult = $this->gameEngine->spin($playerId, $betAmount);
            echo "🎰 旋转结果: " . json_encode($spinResult) . "\n";

            // 发送旋转结果
            $this->sendSpinResult($server, $fd, $spinResult);

            // 广播玩家余额更新
            $roomId = $this->playerManager->getPlayerRoom($playerId);
            if ($roomId) {
                $this->broadcastRoomUpdate($server, $roomId);
            }
        } catch (\Exception $e) {
            $this->sendError($server, $fd, $e->getMessage());
        }
    }

    /**
     * 处理购买奖励
     */
    private function handleBuyBonus($server, $fd, string $playerId, string $bonusType): void
    {
        // 实现奖励购买逻辑
        $this->sendError($server, $fd, '功能开发中');
    }

    /**
     * 发送玩家信息
     */
    private function sendPlayerInfo($server, $fd, array $playerData): void
    {
        $player = new Player();
        $player->setPlayerId($playerData['player_id']);
        $player->setPlayerName($playerData['player_name']);
        $player->setBalance((int)$playerData['balance']);
        $player->setLevel((int)$playerData['level']);
        $player->setExperience((int)$playerData['experience']);
        $player->setLastLoginTime((int)$playerData['last_login_time']);
        $player->setCreatedTime((int)$playerData['created_time']);

        $serverMsg = new ServerMessage();
        $serverMsg->setType('PLAYER_UPDATE');
        $serverMsg->setPlayer($player);

        echo "📤 发送玩家信息给客户端 {$fd}\n";

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送房间列表
     */
    private function sendRoomList($server, $fd): void
    {
        $availableRooms = $this->roomManager->getAvailableRooms();
        // 获取房间数据并转换为 Room 对象
        echo "可用房间数量: " . count($availableRooms) . "\n";
        echo "房间数据: " . json_encode($availableRooms) . "\n";
        $roomObjects = [];
        foreach ($availableRooms as $roomData) {
            $room = new Room();
            $room->setRoomId($roomData['room_id']);
            $room->setRoomName($roomData['room_name']);
            $room->setMaxPlayers((int)$roomData['max_players']);
            $room->setCurrentPlayers((int)$roomData['current_players']);
            $room->setMinBet((int)$roomData['min_bet']);
            $room->setMaxBet((int)$roomData['max_bet']);
            $room->setStatus($roomData['status']);
            $roomObjects[] = $room;
        }
        $serverMsg = new ServerMessage();
        $serverMsg->setType('ROOM_LIST');
        $serverMsg->setAvailableRooms($roomObjects);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送房间加入成功
     */
    private function sendRoomJoined($server, $fd, array $roomData, array $playerData): void
    {
        $room = new Room();
        $room->setRoomId($roomData['room_id']);
        $room->setRoomName($roomData['room_name']);
        $room->setMaxPlayers((int)$roomData['max_players']);
        $room->setCurrentPlayers((int)$roomData['current_players']);
        $room->setMinBet((int)$roomData['min_bet']);
        $room->setMaxBet((int)$roomData['max_bet']);
        $room->setStatus($roomData['status']);

        $player = new Player();
        $player->setPlayerId($playerData['player_id']);
        $player->setPlayerName($playerData['player_name']);
        $player->setBalance((int)$playerData['balance']);

        $serverMsg = new ServerMessage();
        $serverMsg->setType('ROOM_JOINED');
        $serverMsg->setRoom($room);
        $serverMsg->setPlayer($player);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送旋转结果
     */
    private function sendSpinResult($server, $fd, array $spinResult): void
    {
        $spinResultMsg = new SpinResult();
        $spinResultMsg->setReels($this->flattenReels($spinResult['reels']));
        $spinResultMsg->setWinAmount($spinResult['win_amount']);
        $spinResultMsg->setIsBonus($spinResult['is_bonus']);
        $spinResultMsg->setNewBalance($spinResult['new_balance']);

        // 设置赢钱线路
        $winLines = [];
        foreach ($spinResult['win_lines'] as $winLine) {
            $winLineMsg = new WinLine();
            $winLineMsg->setLineIndex($winLine['line_index']);
            $winLineMsg->setSymbol($winLine['symbol']);
            $winLineMsg->setCount($winLine['count']);
            $winLineMsg->setAmount($winLine['amount']);
            $winLines[] = $winLineMsg;
        }
        $spinResultMsg->setWinLines($winLines);
        $serverMsg = new ServerMessage();
        $serverMsg->setType('SPIN_RESULT');
        $serverMsg->setSpinResult($spinResultMsg);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 广播房间更新
     */
    private function broadcastRoomUpdate($server, string $roomId): void
    {
        $this->roomManager->broadcastToRoom($roomId, function ($playerId) use ($server, $roomId) {
            $roomData = $this->roomManager->getRoom($roomId);
            $roomPlayers = $this->roomManager->getRoomPlayers($roomId);

            $room = new Room();
            $room->setRoomId($roomData['room_id']);
            $room->setRoomName($roomData['room_name']);
            $room->setMaxPlayers((int)$roomData['max_players']);
            $room->setCurrentPlayers((int)$roomData['current_players']);
            $room->setMinBet((int)$roomData['min_bet']);
            $room->setMaxBet((int)$roomData['max_bet']);
            $room->setStatus($roomData['status']);

            $serverMsg = new ServerMessage();
            $serverMsg->setType('ROOM_UPDATE');
            $serverMsg->setRoom($room);

            // 找到客户端FD并发送
            foreach ($this->clientPlayers as $fd => $pId) {
                if ($pId === $playerId) {
                    $this->sendToClient($server, $fd, $serverMsg);
                    break;
                }
            }
        });
    }

    /**
     * 发送成功消息
     */
    private function sendSuccess($server, $fd, string $message): void
    {
        $serverMsg = new ServerMessage();
        $serverMsg->setType('SUCCESS');
        $serverMsg->setErrorMessage($message);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送错误消息
     */
    private function sendError($server, $fd, string $error): void
    {
        $serverMsg = new ServerMessage();
        $serverMsg->setType('ERROR');
        $serverMsg->setErrorMessage($error);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送消息到客户端
     */
    private function sendToClient($server, $fd, $message): void
    {
        try {
            if (!$server->isEstablished($fd)) {
                return;
            }

            $msgpackData = MsgPackHelper::encodeProtoMessage($message);
            $result = $server->push($fd, $msgpackData, WEBSOCKET_OPCODE_BINARY);

            if ($result === false) {
                echo "❌ 发送消息到客户端 {$fd} 失败\n";
            }
        } catch (\Exception $e) {
            echo "❌ 发送消息时出错: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 扁平化转轴数组
     */
    private function flattenReels(array $reels): array
    {
        $flattened = [];
        foreach ($reels as $reel) {
            foreach ($reel as $symbol) {
                $flattened[] = $symbol;
            }
        }
        return $flattened;
    }
}
