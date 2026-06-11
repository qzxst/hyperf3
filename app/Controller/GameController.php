<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnOpenInterface;
use Game\Player;
use Game\ClientMessage;
use Game\ServerMessage;
use Game\BattleAction;
use App\Service\RoomManager;

class GameController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    private $roomManager;

    public function __construct()
    {
        $this->roomManager = RoomManager::getInstance();
    }

    public function onOpen($server, $request): void
    {
        $fd = $request->fd;
        echo "🎮 客户端 {$fd} 连接成功\n";

        // 玩家加入房间
        $roomId = $this->roomManager->joinRoom($fd);
        $room = $this->roomManager->getRoom($roomId);

        // 发送房间信息
        $this->sendRoomInfo($server, $fd, $room);

        // 如果房间有2个玩家，自动开始游戏
        if (count($room['players']) >= 2) {
            $this->roomManager->startGame($roomId);
            $this->broadcastGameStart($server, $roomId);
        }
    }

    public function onMessage($server, $frame): void
    {
        $fd = $frame->fd;
        $roomId = $this->roomManager->getPlayerRoom($fd);

        echo "📨 收到来自客户端 {$fd} 的消息，房间: {$roomId}\n";

        try {
            $clientMsg = new ClientMessage();
            $clientMsg->mergeFromString($frame->data);

            echo "📝 消息类型: " . $clientMsg->getType() . "\n";

            switch ($clientMsg->getType()) {
                case 'BATTLE_ACTION':
                    $this->handleBattleAction($server, $fd, $roomId, $clientMsg->getAction());
                    break;
                case 'READY':
                    $this->handlePlayerReady($server, $fd, $roomId);
                    break;
                default:
                    echo "未知消息类型: " . $clientMsg->getType() . "\n";
            }
        } catch (\Exception $e) {
            echo "❌ 处理消息时出错: " . $e->getMessage() . "\n";
            $this->sendErrorMessage($server, $fd, $e->getMessage());
        }
    }

    public function onClose($server, $fd, $reactorId): void
    {
        echo "👋 客户端 {$fd} 断开连接\n";

        $roomId = $this->roomManager->getPlayerRoom($fd);
        $room = $this->roomManager->getRoom($roomId);

        // 获取离开的玩家信息
        $leavingPlayerName = '玩家_' . $fd;
        if ($room && isset($room['players'][(string)$fd])) {
            $leavingPlayerName = $room['players'][(string)$fd]['name'];
        }

        // 移除玩家并获取剩余玩家数量
        $remainingPlayers = $this->roomManager->leaveRoom($fd);

        // 如果房间还有玩家，广播玩家离开的消息
        if ($remainingPlayers > 0) {
            $this->broadcastPlayerLeft($server, $roomId, $leavingPlayerName, $fd);

            // 如果游戏正在进行，则结束游戏，剩余玩家获胜
            if ($room && $room['status'] === 'playing') {
                $remainingPlayerId = array_keys($room['players'])[0]; // 获取剩余的第一个玩家
                $this->broadcastGameOver($server, $roomId, $remainingPlayerId, '对手离开游戏，你获胜了！');
            }
        }
    }

    private function broadcastPlayerLeft($server, string $roomId, string $playerName, int $fd): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        $serverMsg = new ServerMessage();
        $serverMsg->setType('PLAYER_LEFT');
        $serverMsg->setResult("玩家 {$playerName} 离开了游戏");

        foreach ($room['players'] as $player) {
            // 不发送给已经离开的玩家（实际上已经不在房间了）
            if ($player['fd'] == $fd) continue;
            $serverMsg->setIsMyTurn($room['current_turn'] === $player['id']);
            $this->sendToClient($server, $player['fd'], $serverMsg);
        }
    }
    /**
     * 发送房间信息
     */
    private function sendRoomInfo($server, $fd, array $room): void
    {
        $players = [];
        foreach ($room['players'] as $playerData) {
            $player = new Player();
            $player->setId($playerData['id']);
            $player->setName($playerData['name']);
            $player->setHp($playerData['hp']);
            $player->setMaxHp($playerData['maxHp']);
            $players[] = $player;
        }

        $serverMsg = new ServerMessage();
        $serverMsg->setType('ROOM_INFO');
        $serverMsg->setPlayers($players);
        $serverMsg->setResult("房间: {$room['id']}，玩家: " . count($players) . "/2");

        $isMyTurn = ($room['current_turn'] === $fd);
        $serverMsg->setIsMyTurn($isMyTurn);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 广播游戏开始
     */
    private function broadcastGameStart($server, string $roomId): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        foreach ($room['players'] as $player) {
            $players = [];
            foreach ($room['players'] as $p) {
                $playerObj = new Player();
                $playerObj->setId($p['id']);
                $playerObj->setName($p['name']);
                $playerObj->setHp($p['hp']);
                $playerObj->setMaxHp($p['maxHp']);
                $players[] = $playerObj;
            }

            $serverMsg = new ServerMessage();
            $serverMsg->setType('GAME_START');
            $serverMsg->setPlayers($players);
            $serverMsg->setResult('游戏开始！');

            $isMyTurn = ($room['current_turn'] === $player['id']);
            $serverMsg->setIsMyTurn($isMyTurn);

            $this->sendToClient($server, $player['fd'], $serverMsg);

            // 发送回合信息
            if ($isMyTurn) {
                $this->sendTurnMessage($server, $player['fd'], true, '轮到你的回合了！');
            } else {
                $this->sendTurnMessage($server, $player['fd'], false, '等待对手行动...');
            }
        }
    }

    /**
     * 处理战斗动作
     */
    private function handleBattleAction($server, $fd, string $roomId, BattleAction $action): void
    {
        $playerId = (string)$fd;

        $result = $this->roomManager->performAction($roomId, $playerId, $action->getAction(), $action->getTargetId());

        if (!$result['success']) {
            $this->sendErrorMessage($server, $fd, $result['message']);
            return;
        }

        // 广播行动结果
        $this->broadcastActionResult($server, $roomId, $result);

        // 更新游戏状态
        $this->broadcastGameState($server, $roomId);

        // 检查游戏是否结束
        $winner = $this->roomManager->checkGameOver($roomId);
        if ($winner) {
            $this->broadcastGameOver($server, $roomId, $winner);
        } else {
            // 广播回合变更
            $this->broadcastTurnChange($server, $roomId);
        }
    }

    /**
     * 处理玩家准备
     */
    private function handlePlayerReady($server, $fd, string $roomId): void
    {
        // 这里可以实现在玩家准备后手动开始游戏的逻辑
        echo "玩家 {$fd} 准备就绪\n";
    }

    /**
     * 广播行动结果
     */
    private function broadcastActionResult($server, string $roomId, array $result): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        $attackerName = $room['players'][$result['attacker']]['name'];
        $targetName = $room['players'][$result['target']]['name'];

        $message = "{$attackerName} 使用了 {$result['action']}，对 {$targetName} 造成了 {$result['damage']} 点伤害";

        foreach ($room['players'] as $player) {
            $serverMsg = new ServerMessage();
            $serverMsg->setType('ACTION_RESULT');
            $serverMsg->setResult($message);
            $serverMsg->setIsMyTurn($room['current_turn'] === $player['id']);

            $this->sendToClient($server, $player['fd'], $serverMsg);
        }
    }

    /**
     * 广播游戏状态
     */
    private function broadcastGameState($server, string $roomId): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        foreach ($room['players'] as $player) {
            $players = [];
            foreach ($room['players'] as $p) {
                $playerObj = new Player();
                $playerObj->setId($p['id']);
                $playerObj->setName($p['name']);
                $playerObj->setHp($p['hp']);
                $playerObj->setMaxHp($p['maxHp']);
                $players[] = $playerObj;
            }

            $serverMsg = new ServerMessage();
            $serverMsg->setType('BATTLE_UPDATE');
            $serverMsg->setPlayers($players);
            $serverMsg->setIsMyTurn($room['current_turn'] === $player['id']);

            $this->sendToClient($server, $player['fd'], $serverMsg);
        }
    }

    /**
     * 广播回合变更
     */
    private function broadcastTurnChange($server, string $roomId): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        foreach ($room['players'] as $player) {
            $isMyTurn = ($room['current_turn'] === $player['id']);
            $message = $isMyTurn ? '轮到你的回合了！' : '等待对手行动...';

            $this->sendTurnMessage($server, $player['fd'], $isMyTurn, $message);
        }
    }

    /**
     * 发送回合消息
     */
    private function sendTurnMessage($server, $fd, bool $isMyTurn, string $message): void
    {
        $serverMsg = new ServerMessage();
        $serverMsg->setType('TURN_CHANGE');
        $serverMsg->setResult($message);
        $serverMsg->setIsMyTurn($isMyTurn);

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 广播游戏结束
     */
    private function broadcastGameOver($server, string $roomId, string $winnerId, string $reason = ''): void
    {
        $room = $this->roomManager->getRoom($roomId);
        if (!$room) return;

        $winnerName = $room['players'][$winnerId]['name'];

        foreach ($room['players'] as $player) {
            $serverMsg = new ServerMessage();
            $serverMsg->setType('GAME_OVER');
            $serverMsg->setResult($reason ?: "游戏结束！{$winnerName} 获胜！");
            $serverMsg->setIsMyTurn(false);

            $this->sendToClient($server, $player['fd'], $serverMsg);
        }
    }

    /**
     * 发送错误消息
     */
    private function sendErrorMessage($server, $fd, string $error): void
    {
        $serverMsg = new ServerMessage();
        $serverMsg->setType('ERROR');
        $serverMsg->setResult($error);

        $roomId = $this->roomManager->getPlayerRoom($fd);
        $room = $this->roomManager->getRoom($roomId);
        if ($room) {
            $serverMsg->setIsMyTurn($room['current_turn'] === $fd);
        }

        $this->sendToClient($server, $fd, $serverMsg);
    }

    /**
     * 发送消息给客户端
     */
    private function sendToClient($server, $fd, ServerMessage $message): void
    {
        try {
            $data = $message->serializeToString();
            $result = $server->push($fd, $data, WEBSOCKET_OPCODE_BINARY);

            if ($result === false) {
                echo "❌ 发送消息到客户端 {$fd} 失败\n";
            } else {
                $type = $message->getType();
                $isMyTurn = $message->getIsMyTurn() ? 'true' : 'false';
                echo "✅ 发送成功 - 客户端: {$fd}, 类型: {$type}, is_my_turn: {$isMyTurn}\n";
            }
        } catch (\Exception $e) {
            echo "❌ 发送消息时出错: " . $e->getMessage() . "\n";
        }
    }
}
