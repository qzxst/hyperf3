<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LtvStatsService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\Inject;

#[Command]
class TestLtvFix extends HyperfCommand
{
    #[Inject]
    private LtvStatsService $ltvStatsService;

    public function __construct()
    {
        parent::__construct('test:ltv-fix');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('测试 LTV 统计修复');
    }

    public function handle()
    {
        $this->info('开始测试 LTV 统计修复...');

        // 测试单日统计
        $testDate = date('Y-m-d', strtotime('-1 day'));
        $this->info("测试日期: {$testDate}");

        $result = $this->ltvStatsService->calculateLTVForDate($testDate);

        if ($result['success']) {
            $this->info("✅ 测试成功: " . $result['message']);
            $this->info("处理记录数: " . $result['processed_count']);
        } else {
            $this->error("❌ 测试失败: " . $result['message']);
        }

        $this->info('LTV 统计修复测试完成!');
    }
}
