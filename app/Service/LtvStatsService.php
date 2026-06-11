<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Ltv90dStat;
use App\Model\LtvStatsLog;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class LtvStatsService
{
    /**
     * 计算指定日期的 LTV 数据
     */
    public function calculateLTVForDate(string $statDate): array
    {
        $logId = $this->startLog($statDate);
        $processedCount = 0;

        try {
            Db::beginTransaction();

            // 删除指定日期的旧数据（支持重跑）
            Ltv90dStat::where('stat_date', $statDate)->delete();

            // 计算 LTV 统计数据
            $sql = "
            INSERT INTO ltv_90d_stats (
                stat_date, register_date, promoter_id, channel_id, register_count,
                day1_ltv, day3_ltv, day7_ltv, day30_ltv, day60_ltv, day90_ltv,
                total_revenue, avg_ltv, created_at, updated_at
            )
            SELECT
                ? AS stat_date,
                DATE(u.registerTime) AS register_date,
                u.promoterId,
                u.channelId,
                COUNT(DISTINCT u.uid) AS register_count,

                -- 1日LTV：注册后1天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 1 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day1_ltv,

                -- 3日LTV：注册后3天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 3 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day3_ltv,

                -- 7日LTV：注册后7天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 7 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day7_ltv,

                -- 30日LTV：注册后30天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 30 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day30_ltv,

                -- 60日LTV：注册后60天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 60 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day60_ltv,

                -- 90日LTV：注册后90天内的收入
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 90 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS day90_ltv,

                -- 累计总收入（90天内）
                SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 90 DAY)
                        AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) AS total_revenue,

                -- 平均LTV
                CASE WHEN COUNT(DISTINCT u.uid) > 0
                     THEN SUM(CASE WHEN o.createTime <= DATE_ADD(u.registerTime, INTERVAL 90 DAY)
                                  AND o.status = 'success' THEN o.price * o.num * o.discount ELSE 0 END) / COUNT(DISTINCT u.uid)
                     ELSE 0 END AS avg_ltv,
                NOW(), NOW()

            FROM user_table u
            LEFT JOIN order_table o ON u.uid = o.uid
            WHERE DATE(u.registerTime) <= ?
              AND DATE(u.registerTime) >= DATE_SUB(?, INTERVAL 90 DAY)
              AND u.registerTime IS NOT NULL
            GROUP BY DATE(u.registerTime), u.promoterId, u.channelId
            ";

            $processedCount = Db::affectingStatement($sql, [$statDate, $statDate, $statDate]);

            Db::commit();
            $this->finishLog($logId, 'success', $processedCount);

            return [
                'success' => true,
                'message' => "日期 {$statDate} 处理完成，影响记录: {$processedCount} 条",
                'processed_count' => $processedCount
            ];
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->finishLog($logId, 'failed', 0, $e->getMessage());

            return [
                'success' => false,
                'message' => "日期 {$statDate} 处理失败: " . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取下一个需要统计的日期
     */
    public function getNextStatDate(): ?string
    {
        $lastStat = LtvStatsLog::where('status', 'success')
            ->orderBy('stat_date', 'desc')
            ->first();

        if (!$lastStat) {
            // 如果没有历史记录，从90天前开始
            return date('Y-m-d', strtotime('-90 days'));
        }

        $nextDate = date('Y-m-d', strtotime($lastStat->stat_date . ' +1 day'));

        // 如果下一个日期大于当前日期，返回null
        if ($nextDate > date('Y-m-d')) {
            return null;
        }

        return $nextDate;
    }

    /**
     * 开始日志记录
     */
    private function startLog(string $statDate): int
    {
        $log = new LtvStatsLog();
        $log->stat_date = $statDate;
        $log->start_time = date('Y-m-d H:i:s');
        $log->status = 'running';
        $log->save();

        return $log->id;
    }

    /**
     * 完成日志记录
     */
    private function finishLog(int $logId, string $status, int $processedCount = 0, string $errorMessage = ''): void
    {
        $log = LtvStatsLog::find($logId);
        if ($log) {
            $log->end_time = date('Y-m-d H:i:s');
            $log->status = $status;
            $log->processed_count = $processedCount;
            $log->error_message = $errorMessage;
            $log->save();
        }
    }

    /**
     * 手动重跑日期范围
     */
    public function rerunDateRange(string $startDate, string $endDate): array
    {
        $results = [];
        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            $result = $this->calculateLTVForDate($currentDate);
            $results[$currentDate] = $result;
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        return $results;
    }
}
