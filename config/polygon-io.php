<?php

return [
    'base_uri' => env('POLYGON_IO_BASE_URI', 'https://api.polygon.io'),
    /**
     * https://polygon.io/dashboard/api-keys
     */
    'api_key' => env('POLYGON_IO_API_KEY'),
    'timeout' => env('POLYGON_IO_TIMEOUT', 10),
    'connect_timeout' => env('POLYGON_IO_CONNECT_TIMEOUT', 10),
];
