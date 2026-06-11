<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Redis\Redis;
use Hyperf\Context\ApplicationContext;

class RankingService
{
    protected Redis $redis;
    protected string $key;

    // 时间戳最大值 (支持到 2038-01-19)
    protected const MAX_TIMESTAMP = 2147483647;

    public function __construct(string $key = 'leaderboard')
    {
        $this->redis = ApplicationContext::getContainer()->get(Redis::class);
        $this->key = $key;
    }

    /**
     * 添加或更新用户分数（后来者居上）
     */
    public function addScore(string $member, int $score): bool
    {
        // 获取当前时间戳（秒级）
        $timestamp = time();

        // 计算反向时间戳（确保后来者的值更大）
        $reverseTimestamp = self::MAX_TIMESTAMP - $timestamp;

        // 组合分数：实际分数放在高位，反向时间戳放在低位
        $combinedScore = $score * 1e10 + $reverseTimestamp;

        return (bool) $this->redis->zAdd($this->key, (string) $combinedScore, $member);
    }

    /**
     * 获取用户排名（从0开始，0表示第一名）
     */
    public function getRank(string $member): ?int
    {
        $rank = $this->redis->zRevRank($this->key, $member);
        return $rank === false ? null : (int) $rank;
    }

    /**
     * 获取用户分数（原始分数）
     */
    public function getScore(string $member): ?int
    {
        $combinedScore = $this->redis->zScore($this->key, $member);
        if ($combinedScore === false) {
            return null;
        }

        return (int) ($combinedScore / 1e10);
    }

    /**
     * 获取排行榜前N名
     */
    public function getTopN(int $n, bool $withScores = false): array
    {
        $result = $this->redis->zRevRange($this->key, 0, $n - 1, $withScores);

        if (!$withScores) {
            return $result;
        }

        // 处理返回的分数，转换为原始分数
        $formatted = [];
        foreach ($result as $member => $combinedScore) {
            $formatted[$member] = (int) ($combinedScore / 1e10);
        }

        return $formatted;
    }

    /**
     * 获取排行榜所有成员（按排名倒序）
     */
    public function getAll(): array
    {
        return $this->redis->zRevRange($this->key, 0, -1, true);
    }

    /**
     * 从组合分数中解析出原始分数和时间戳
     */
    public function parseScore(float $combinedScore): array
    {
        $originalScore = (int) ($combinedScore / 1e10);
        $reverseTimestamp = (int) ($combinedScore % (int)1e10);
        $timestamp = self::MAX_TIMESTAMP - $reverseTimestamp;

        return [
            'score' => $originalScore,
            'timestamp' => $timestamp,
            'combined_score' => $combinedScore
        ];
    }

    /**
     * 递增用户分数
     */
    public function incrScore(string $member, int $increment): bool
    {
        // 需要先获取当前分数，然后重新计算组合分数
        $currentScore = $this->getScore($member) ?? 0;
        $newScore = $currentScore + $increment;

        return $this->addScore($member, $newScore);
    }

    /**
     * 删除用户
     */
    public function removeMember(string $member): bool
    {
        return (bool) $this->redis->zRem($this->key, $member);
    }

    /**
     * 获取排行榜成员数量
     */
    public function getCount(): int
    {
        return $this->redis->zCard($this->key);
    }

    /**
     * 删除整个排行榜
     */
    public function clear(): bool
    {
        return (bool) $this->redis->del($this->key);
    }
}
