<?php

declare(strict_types=1);

namespace App\Event;

class CrontabProcessed
{
    public $crontab;
    public $result;
    public $startTime;
    public $endTime;

    public function __construct($crontab, $result, $startTime, $endTime = null)
    {
        $this->crontab = $crontab;
        $this->result = $result;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }
}
