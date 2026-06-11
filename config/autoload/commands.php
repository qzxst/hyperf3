<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    App\Command\LtvStatsCommand::class,
    App\Command\CheckDatabaseData::class,
    App\Command\CrontabList::class,
    App\Command\CrontabStatus::class,
    App\Command\CrontabLogs::class,
    App\Command\CrontabMonitor::class,
    App\Command\TestLtvFix::class,
    App\Command\CrontabHealth::class,
    App\Command\DataGeneratorCommand::class,
    App\Command\DataMonitorCommand::class,
    App\Command\DbSelectCommand::class,
];
