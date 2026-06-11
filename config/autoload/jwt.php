<?php

return [
    'secret' => env('JWT_SECRET', 'your-secret-key'),
    'ttl' => env('JWT_TTL', 3600), // Token 有效期，单位为秒
    'algorithm' => env('JWT_ALGORITHM', 'HS256'), // 加密算法
];
