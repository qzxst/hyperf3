<?php
// config/autoload/crontab.php
use Hyperf\Crontab\Crontab;

return [
    'enable' => true,
    // 通过配置文件定义的定时任务
    'crontab' => [
        // Command类型定时任务
        // (new Crontab())->setType('command')->setName('Bar')->setRule('* * * * *')->setCallback([
        //     'command' => 'swiftmailer:spool:send',
        //     // (optional) arguments
        //     'fooArgument' => 'barValue',
        //     // (optional) options
        //     '--message-limit' => 1,
        //     // 记住要加上，否则会导致主进程退出
        //     '--disable-event-dispatcher' => true,
        // ])->setEnvironments(['develop', 'production']),
        // Closure 类型定时任务 (仅在 Coroutine style server 中支持)
        // (new Crontab())->setType('closure')->setName('Closure')->setRule('* * * * *')->setCallback(function () {
        //     var_dump(date('Y-m-d H:i:s'));
        // })->setEnvironments(['develop', 'production']),
        // (new Crontab())
        //     ->setName('LTV90dStats')
        //     ->setRule('*/5 * * * *')
        //     ->setCallback([App\Task\LtvStatsTask::class, 'execute'])
        //     ->setMemo('LTV 90天统计任务 - 每5分钟执行一次'),
        // 每秒数据生成任务
        // (new Crontab())
        //     ->setName('SecondDataGenerator')
        //     ->setRule('* * * * * *')
        //     ->setCallback([App\Task\SecondDataGeneratorTask::class, 'execute'])
        //     ->setMemo('每秒生成用户和订单数据')
        //     ->setEnable(env('ENABLE_SECOND_DATA_GENERATOR', false)),
    ],
];
