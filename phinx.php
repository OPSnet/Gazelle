<?php

require_once(__DIR__.'/classes/config.php');

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'gazelle',
        'gazelle' => [
            'adapter' => 'mysql',
            'host' => SQLHOST,
            'port' => SQLPORT,
            'name' => SQLDB,
            'user' => SQL_PHINX_USER,
            'pass' => SQL_PHINX_PASS,
            'charset' => 'utf8mb4'
        ],
        'pg' => [
            'default_migration_table' => 'phinx.log',
            'adapter' => 'pgsql',
            'host' => GZPG_HOST,
            'port' => GZPG_PORT,
            'name' => GZPG_DB,
            'user' => GZPG_USER,
            'pass' => GZPG_PASSWORD,
        ],
    ],
    'version_order' => 'creation'
];
