<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Context\ApplicationContext;


class BaiduMonitorJob extends Job
{

    public $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        co(function () {
            // 保存到日志文件内
            ApplicationContext::getContainer()->get(LoggerFactory::class)->get('default')->info('BaiduMonitorJob executed', $this->params);
        });
        // 这里可以添加具体的业务逻辑，比如调用百度监控
        co(function () {
            ApplicationContext::getContainer()->get(LoggerFactory::class)->get('queue')->info('BaiduMonitorJob executed', $this->params);
        });
    }
}
