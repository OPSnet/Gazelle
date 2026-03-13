<?php

require __DIR__ . '/../lib/config.php';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/pg-migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/pg-seeds',
    ],
    'environments' => [
        'migration_table'     => 'phinxlog',
        'default_environment' => 'pg',
        'pg' => [
            'adapter' => 'pgsql',
            'host'    => PG_HOST,
            'port'    => PG_PORT,
            'name'    => PG_DB,
            'user'    => PG_RW_USER,
            'pass'    => PG_RW_PASS,
        ],
    ],
    'version_order' => 'creation',
    'feature_flags' => [
        'unsigned_primary_keys' => false,
        'column_null_default'   => false,
    ],
];
