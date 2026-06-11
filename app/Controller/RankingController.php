<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RankingService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;

#[Controller(prefix: "/rank")]
class RankingController extends AbstractController
{
    #[Inject]
    protected RankingService $rankingService;

    #[PostMapping("add")]
    public function addScore()
    {
        $member = (string) $this->request->input('user_id');
        $score = (int) $this->request->input('score', 0);

        $result = $this->rankingService->addScore($member, $score);

        return [
            'code' => $result ? 200 : 500,
            'message' => $result ? 'success' : 'failed'
        ];
    }

    #[GetMapping("top")]
    public function getTop()
    {
        $top = $this->rankingService->getTopN(10, true);

        return [
            'code' => 200,
            'data' => $top
        ];
    }

    #[GetMapping("user-rank")]
    public function getUserRank()
    {
        $member = (string) $this->request->input('user_id');
        $rank = $this->rankingService->getRank($member);
        $score = $this->rankingService->getScore($member);

        return [
            'code' => 200,
            'data' => [
                'user_id' => $member,
                'rank' => $rank === null ? null : $rank + 1, // 转换为从1开始排名
                'score' => $score
            ]
        ];
    }

    #[GetMapping("test")]
    public function testRanking()
    {
        // 测试数据：验证后来者居上
        $this->rankingService->clear();

        // 用户1先获得100分
        $this->rankingService->addScore('user1', 100);
        sleep(1);

        // 用户2后获得100分（应该排在用户1前面）
        $this->rankingService->addScore('user2', 100);

        // 用户3获得更高的分数
        $this->rankingService->addScore('user3', 150);

        $top = $this->rankingService->getTopN(5, true);
        $user1Rank = $this->rankingService->getRank('user1');
        $user2Rank = $this->rankingService->getRank('user2');

        return [
            'code' => 200,
            'data' => [
                'top_ranking' => $top,
                'user1_rank' => $user1Rank === null ? null : $user1Rank + 1,
                'user2_rank' => $user2Rank === null ? null : $user2Rank + 1,
                'expected' => 'user2 should rank higher than user1 when same score'
            ]
        ];
    }
}
