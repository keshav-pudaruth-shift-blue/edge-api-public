<?php

return [
    'base_uri' => env('IB_GATEWAY_BASE_URI', 'http://localhost:3001/ib/'),
    'timeout' => env('IB_GATEWAY_TIMEOUT', 30),
    'connect_timeout' => env('IB_GATEWAY_CONNECT_TIMEOUT', 5),
    'max_connections' => env('IB_GATEWAY_MAX_CONNECTIONS', 30),
];
