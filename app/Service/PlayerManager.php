<?php

namespace App\Service;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class PlayerManager
{
    // 单例实例
    private static $instance;

    // 玩家数组，key为fd，value为Player对象
    private $players = [];

    // 使用Channel实现简单的锁
    private $channel;

    private function __construct()
    {
        // 初始化一个容量为1的Channel，用作锁
        $this->channel = new Channel(1);
        $this->channel->push(true); // 放入一个true，表示锁可用
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addPlayer($fd, $player)
    {
        // 加锁
        $this->channel->pop();
        $this->players[$fd] = $player;
        // 释放锁
        $this->channel->push(true);
    }

    public function removePlayer($fd)
    {
        $this->channel->pop();
        unset($this->players[$fd]);
        $this->channel->push(true);
    }

    public function getPlayers()
    {
        $this->channel->pop();
        $players = $this->players;
        $this->channel->push(true);
        return $players;
    }

    public function getPlayer($fd)
    {
        $this->channel->pop();
        $player = $this->players[$fd] ?? null;
        $this->channel->push(true);
        return $player;
    }
}
