<?php

require_once(__DIR__ . '/../lib/config.php');

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/phinx-pg/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/phinx-pg/seeds'
    ],
    'environments' => [
        'migration_table'     => 'phinxlog',
        'default_environment' => 'pg',
        'pg' => [
            'adapter'         => 'pgsql',
            'host'            => GZPG_HOST,
            'port'            => GZPG_PORT,
            'name'            => GZPG_DB,
            'user'            => GZPG_USER,
            'pass'            => GZPG_PASSWORD,
        ],
    ],
    'version_order' => 'creation',
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default'   => false,
    ],
];
