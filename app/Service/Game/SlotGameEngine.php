<?php
declare(strict_types=1);

namespace App\Service\Game;

use Hyperf\Redis\Redis;
use Hyperf\Context\ApplicationContext;

class SlotGameEngine
{
    private Redis $redis;
    private const SYMBOLS_KEY = 'slot:symbols';

    // 默认符号配置
    private array $defaultSymbols = [
        ['id' => 'cherry', 'name' => '樱桃', 'weight' => 20, 'multiplier' => 2, 'wild' => false, 'scatter' => false],
        ['id' => 'lemon', 'name' => '柠檬', 'weight' => 18, 'multiplier' => 3, 'wild' => false, 'scatter' => false],
        ['id' => 'orange', 'name' => '橙子', 'weight' => 16, 'multiplier' => 4, 'wild' => false, 'scatter' => false],
        ['id' => 'plum', 'name' => '李子', 'weight' => 14, 'multiplier' => 5, 'wild' => false, 'scatter' => false],
        ['id' => 'grapes', 'name' => '葡萄', 'weight' => 12, 'multiplier' => 8, 'wild' => false, 'scatter' => false],
        ['id' => 'watermelon', 'name' => '西瓜', 'weight' => 10, 'multiplier' => 10, 'wild' => false, 'scatter' => false],
        ['id' => 'seven', 'name' => '七', 'weight' => 5, 'multiplier' => 20, 'wild' => false, 'scatter' => false],
        ['id' => 'wild', 'name' => '万能', 'weight' => 3, 'multiplier' => 0, 'wild' => true, 'scatter' => false],
        ['id' => 'scatter', 'name' => '散射', 'weight' => 2, 'multiplier' => 0, 'wild' => false, 'scatter' => true],
    ];

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
        $this->initializeSymbols();
    }

    /**
     * 初始化符号配置
     */
    private function initializeSymbols(): void
    {
        if (!$this->redis->exists(self::SYMBOLS_KEY)) {
            foreach ($this->defaultSymbols as $symbol) {
                $this->redis->hSet(self::SYMBOLS_KEY, $symbol['id'], json_encode($symbol));
            }
        }
    }

    /**
     * 执行旋转
     */
    public function spin(string $playerId, int $betAmount): array
    {
        $playerManager = new PlayerManager();

        // 检查余额
        $balance = $playerManager->getBalance($playerId);
        if ($balance < $betAmount) {
            throw new \Exception('余额不足');
        }

        // 扣款
        $playerManager->updateBalance($playerId, -$betAmount);

        // 生成旋转结果
        $reels = $this->generateReels();
        $winResult = $this->calculateWin($reels, $betAmount);

        // 派彩
        if ($winResult['win_amount'] > 0) {
            $playerManager->updateBalance($playerId, $winResult['win_amount']);
            $playerManager->addExperience($playerId, $winResult['win_amount']);
        }

        $newBalance = $playerManager->getBalance($playerId);

        return [
            'reels' => $reels,
            'win_amount' => $winResult['win_amount'],
            'win_lines' => $winResult['win_lines'],
            'is_bonus' => $winResult['is_bonus'],
            'new_balance' => $newBalance,
            'bet_amount' => $betAmount
        ];
    }

    /**
     * 生成转轴结果
     */
    private function generateReels(): array
    {
        $reels = [];
        $symbols = $this->getAllSymbols();

        // 3x5 的老虎机网格
        for ($reel = 0; $reel < 5; $reel++) {
            $reelSymbols = [];
            for ($row = 0; $row < 3; $row++) {
                $reelSymbols[] = $this->getRandomSymbol($symbols);
            }
            $reels[] = $reelSymbols;
        }

        return $reels;
    }

    /**
     * 获取随机符号
     */
    private function getRandomSymbol(array $symbols): string
    {
        $totalWeight = array_sum(array_column($symbols, 'weight'));
        $random = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($symbols as $symbol) {
            $currentWeight += $symbol['weight'];
            if ($random <= $currentWeight) {
                return $symbol['id'];
            }
        }

        return $symbols[0]['id'];
    }

    /**
     * 计算赢钱结果
     */
    private function calculateWin(array $reels, int $betAmount): array
    {
        $winLines = [];
        $totalWin = 0;
        $isBonus = false;

        // 检查赢钱线路
        $paylines = $this->getPaylines();
        $symbols = $this->getAllSymbols();

        foreach ($paylines as $lineIndex => $positions) {
            $lineSymbols = [];
            foreach ($positions as $position) {
                $lineSymbols[] = $reels[$position[0]][$position[1]];
            }

            $winSymbol = $this->checkWinningLine($lineSymbols);
            if ($winSymbol) {
                $symbolData = $symbols[$winSymbol];
                $winAmount = $betAmount * $symbolData['multiplier'];
                $totalWin += $winAmount;

                $winLines[] = [
                    'line_index' => $lineIndex,
                    'symbol' => $winSymbol,
                    'count' => 3, // 简化为固定3个
                    'amount' => $winAmount
                ];
            }
        }

        // 检查散射奖励
        $scatterCount = $this->countScatters($reels);
        if ($scatterCount >= 3) {
            $isBonus = true;
            $totalWin += $betAmount * 10; // 散射奖励
        }

        return [
            'win_amount' => $totalWin,
            'win_lines' => $winLines,
            'is_bonus' => $isBonus
        ];
    }

    /**
     * 获取所有符号
     */
    private function getAllSymbols(): array
    {
        $symbolsData = $this->redis->hGetAll(self::SYMBOLS_KEY);
        $symbols = [];

        foreach ($symbolsData as $symbolJson) {
            $symbol = json_decode($symbolJson, true);
            $symbols[$symbol['id']] = $symbol;
        }

        return $symbols;
    }

    /**
     * 获取赢钱线路定义
     */
    private function getPaylines(): array
    {
        return [
            // 水平线
            0 => [[0,0], [1,0], [2,0], [3,0], [4,0]], // 中间行
            1 => [[0,1], [1,1], [2,1], [3,1], [4,1]], // 上行
            2 => [[0,2], [1,2], [2,2], [3,2], [4,2]], // 下行

            // V型线
            3 => [[0,0], [1,1], [2,2], [3,1], [4,0]],
            4 => [[0,2], [1,1], [2,0], [3,1], [4,2]],
        ];
    }

    /**
     * 检查赢钱线路
     */
    private function checkWinningLine(array $symbols): ?string
    {
        $firstSymbol = $symbols[0];
        $count = 1;

        for ($i = 1; $i < count($symbols); $i++) {
            if ($symbols[$i] === $firstSymbol || $symbols[$i] === 'wild') {
                $count++;
            } else {
                break;
            }
        }

        return $count >= 3 ? $firstSymbol : null;
    }

    /**
     * 计算散射数量
     */
    private function countScatters(array $reels): int
    {
        $count = 0;
        foreach ($reels as $reel) {
            foreach ($reel as $symbol) {
                if ($symbol === 'scatter') {
                    $count++;
                }
            }
        }
        return $count;
    }
}
