<?php

namespace App\Factory;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Adapter;

class PrometheusCollectorRegistryFactory
{
    public function __invoke()
    {

        $storage = new InMemory();
        return new CollectorRegistry($storage);
    }
}
