<?php

require_once(__DIR__ . '/lib/config.php');

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/misc/phinx/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/misc/phinx/seeds'
    ],
    'environments' => [
        'migration_table'     => 'phinxlog',
        'default_environment' => 'gazelle',
        'gazelle' => [
            'adapter' => 'mysql',
            'host'    => SQLHOST,
            'port'    => SQLPORT,
            'name'    => SQLDB,
            'user'    => SQL_PHINX_USER,
            'pass'    => SQL_PHINX_PASS,
            'charset' => 'utf8mb4'
        ],
    ],
    'version_order' => 'creation',
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default'   => false,
    ],
];
