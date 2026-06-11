<?php
declare(strict_types=1);

namespace App\Event;

class LtvStatsEvent
{
    public $statDate;
    public $result;
    public $type;

    public function __construct(string $statDate, array $result, string $type = 'scheduled')
    {
        $this->statDate = $statDate;
        $this->result = $result;
        $this->type = $type;
    }
}
