<?php

declare(strict_types=1);

use Hyperf\Metric\Adapter\Prometheus\Constants;

return [
    // // 使用的指标适配器，默认使用 Prometheus
    // 'default' => env('METRIC_DRIVER', 'prometheus'),
    // // 使用 Prometheus 时的配置
    // 'use_standalone_process' => env('METRIC_USE_STANDALONE_PROCESS', true),
    // 'enable_default_metric' => env('METRIC_ENABLE_DEFAULT_METRIC', true),
    // 'default_metric_interval' => env('METRIC_DEFAULT_METRIC_INTERVAL', 5),
    // 'prometheus' => [
    //     // Prometheus 配置
    //     'driver' => Hyperf\Metric\Adapter\Prometheus\MetricFactory::class,
    //     'mode' => Constants::SCRAPE_MODE,
    //     'namespace' => env('APP_NAME', 'skeleton'),
    //     'scrape_host' => env('PROMETHEUS_SCRAPE_HOST', '0.0.0.0'),
    //     'scrape_port' => env('PROMETHEUS_SCRAPE_PORT', '9502'),
    //     'scrape_path' => env('PROMETHEUS_SCRAPE_PATH', '/metrics'),
    // ],
    'default' => 'noop',
    'use_standalone_process' => false,
    'enable_default_metric' => false,
    'metric' => [
        'noop' => [
            'driver' => Hyperf\Metric\Adapter\NoOp\MetricFactory::class,
        ],
    ],
];
