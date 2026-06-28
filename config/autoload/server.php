<?php

declare(strict_types=1);

use Hyperf\Framework\Bootstrap\FinishCallback;
use Hyperf\Framework\Bootstrap\PipeMessageCallback;
use Hyperf\Framework\Bootstrap\TaskCallback;
use Hyperf\Framework\Bootstrap\WorkerExitCallback;
use Hyperf\Framework\Bootstrap\WorkerStartCallback;
use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Swoole\Constant;

return [
    'mode' => SWOOLE_PROCESS,

    'servers' => [
        [
            'name'       => 'http',
            'type'       => Server::SERVER_HTTP,
            'host'       => '0.0.0.0',
            'port'       => (int) env('SERVER_HTTP_PORT', 9501),
            'sock_type'  => SWOOLE_SOCK_TCP,
            'callbacks'  => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
            'options' => [
                'enable_request_lifecycle' => true,   // 建议开启，便于中间件/生命周期管理
            ],
        ],
        [
            'name' => 'ws',
            'type' => Server::SERVER_WEBSOCKET,
            'host' => '0.0.0.0',
            'port' => 9502,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_HAND_SHAKE => [Hyperf\WebSocketServer\Server::class, 'onHandShake'],
                Event::ON_MESSAGE => [Hyperf\WebSocketServer\Server::class, 'onMessage'],
                Event::ON_CLOSE => [Hyperf\WebSocketServer\Server::class, 'onClose'],
            ],
        ],
    ],

    'settings' => [
        Constant::OPTION_ENABLE_COROUTINE     => true,
        Constant::OPTION_WORKER_NUM           => swoole_cpu_num() * 2,   // 根据 CPU 调整
        Constant::OPTION_TASK_WORKER_NUM      => swoole_cpu_num() * 2,
        Constant::OPTION_TASK_ENABLE_COROUTINE => true,

        Constant::OPTION_PID_FILE             => BASE_PATH . '/runtime/hyperf.pid',
        Constant::OPTION_OPEN_TCP_NODELAY     => true,
        Constant::OPTION_MAX_COROUTINE        => 200000,      // 根据业务调大
        Constant::OPTION_MAX_REQUEST          => 100000,
        Constant::OPTION_OPEN_HTTP2_PROTOCOL  => true,

        // 信号处理优化（减少 warning）
        Constant::OPTION_ENABLE_SIGNALFD      => false,   // 关键：关闭 signalfd
        Constant::OPTION_REACTOR_NUM          => swoole_cpu_num(),

        // 日志与缓冲
        Constant::OPTION_LOG_FILE             => BASE_PATH . '/runtime/logs/swoole.log',
        Constant::OPTION_BUFFER_OUTPUT_SIZE   => 32 * 1024 * 1024,
        Constant::OPTION_SOCKET_BUFFER_SIZE   => 32 * 1024 * 1024,
    ],

    'callbacks' => [
        Event::ON_WORKER_START => [WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT  => [WorkerExitCallback::class, 'onWorkerExit'],
        Event::ON_TASK         => [TaskCallback::class, 'onTask'],
        Event::ON_FINISH       => [FinishCallback::class, 'onFinish'],
    ],
];
