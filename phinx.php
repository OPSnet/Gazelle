<?php

require __DIR__ . '/lib/config.php';

return [
    'paths' => [
        'migrations' => __DIR__ . '/misc/my-migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'gazelle',
        'gazelle' => [
            'adapter' => 'mysql',
            'host'    => MYSQL_HOST,
            'port'    => MYSQL_PORT,
            'name'    => MYSQL_DB,
            'user'    => MYSQL_PHINX_USER,
            'pass'    => MYSQL_PHINX_PASS,
            'charset' => 'utf8mb4'
        ],
    ],
    'version_order' => 'creation',
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default'   => false,
    ],
];
