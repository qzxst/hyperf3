<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Crontab\Crontab;
use Hyperf\Crontab\Parser;
use Psr\Container\ContainerInterface;

#[Command]
class CrontabList extends HyperfCommand
{
    protected ContainerInterface $container;
    protected Parser $parser;

    public function __construct(ContainerInterface $container, Parser $parser)
    {
        parent::__construct('crontab:list');
        $this->container = $container;
        $this->parser = $parser;
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('查看所有定时任务');
    }

    public function handle()
    {
        $this->line('📅 Hyperf 定时任务列表', 'info');
        $this->line(str_repeat('=', 80), 'info');

        // 获取配置的定时任务
        $config = $this->container->get(\Hyperf\Contract\ConfigInterface::class);
        $crontabs = $config->get('crontab.crontab', []);

        if (empty($crontabs)) {
            $this->line('❌ 没有配置任何定时任务', 'error');
            return;
        }

        $this->line(sprintf('共 %d 个定时任务:', count($crontabs)), 'info');
        $this->line('');

        $rows = [];
        foreach ($crontabs as $index => $crontab) {
            if ($crontab instanceof Crontab) {
                $nextExecution = $this->getNextExecutionTime($crontab->getRule());

                $rows[] = [
                    'index' => $index + 1,
                    'name' => $crontab->getName() ?: 'N/A',
                    'rule' => $crontab->getRule(),
                    'callback' => $this->formatCallback($crontab->getCallback()),
                    'memo' => $crontab->getMemo() ?: '无描述',
                    'next_execution' => $nextExecution,
                    'enable' => $crontab->isEnable() ? '✅' : '❌',
                ];
            }
        }

        $this->table(
            ['序号', '任务名称', '执行规则', '回调方法', '描述', '下次执行', '状态'],
            $rows
        );

        $this->line('');
        $this->line('💡 执行规则说明:', 'info');
        $this->line('  * * * * *');
        $this->line('  | | | | |');
        $this->line('  | | | | +---- 星期几 (0 - 7) (0和7都代表周日)');
        $this->line('  | | | +------ 月份 (1 - 12)');
        $this->line('  | | +-------- 日期 (1 - 31)');
        $this->line('  | +---------- 小时 (0 - 23)');
        $this->line('  +------------ 分钟 (0 - 59)');
    }

    private function formatCallback($callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            return $callback[0] . '::' . $callback[1];
        }

        return '未知回调';
    }

    private function getNextExecutionTime(string $rule): string
    {
        try {
            $crontab = new Crontab();
            $crontab->setRule($rule);
            $time = $this->parser->parse($rule, time());
            if (!empty($time) && isset($time[0])) {
                $timestamp = $time[0];

                // 如果已经是 Carbon 对象，直接格式化
                if ($timestamp instanceof \Carbon\Carbon) {
                    return $timestamp->format('Y-m-d H:i:s');
                }

                // 如果是整数时间戳，转换为 Carbon 再格式化
                if (is_int($timestamp) || is_numeric($timestamp)) {
                    return \Carbon\Carbon::createFromTimestamp((int)$timestamp)->format('Y-m-d H:i:s');
                }

                return '未知时间类型: ' . gettype($timestamp);
            } else {
                return '无执行时间';
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '解析失败';
    }
}
