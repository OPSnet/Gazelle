<?php

require_once(__DIR__ . '../../lib/config.php');

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/phinx-pg/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/phinx-pg/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'pg',
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
