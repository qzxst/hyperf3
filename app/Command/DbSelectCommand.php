<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class DbSelectCommand extends HyperfCommand
{
    protected ?string $name = 'db:select';

    public function configure()
    {
        parent::configure();
        $this->setDescription('执行 SQL 查询语句');
        $this->addArgument('sql', InputArgument::REQUIRED, '要执行的 SQL 语句');
        $this->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, '数据库连接', 'default');
    }

    public function handle()
    {
        $sql = $this->input->getArgument('sql');
        $connection = $this->input->getOption('connection');

        try {
            $this->info("执行 SQL: {$sql}");

            $results = Db::connection($connection)->select($sql);

            if (empty($results)) {
                $this->info('查询结果为空');
                return;
            }

            // 显示表头
            $headers = array_keys((array) $results[0]);
            $this->table($headers, array_map(function ($item) {
                return (array) $item;
            }, $results));

            $this->info("共查询到 " . count($results) . " 条记录");
        } catch (\Exception $e) {
            $this->error("执行失败: " . $e->getMessage());
        }
    }
}
