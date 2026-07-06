<?php

return [
    'driver' => 'pgsql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '5432',
    'database' => getenv('DB_DATABASE') ?: 'lector_resoluciones',
    'username' => getenv('DB_USERNAME') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: '',
];
