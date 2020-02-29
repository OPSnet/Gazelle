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
        'default_database' => 'gazelle',
        'gazelle' => [
            'adapter' => 'mysql',
            'host' => SQLHOST,
            'name' => SQLDB,
            'user' => SQL_PHINX_USER,
            'pass' => SQL_PHINX_PASS,
            'port' => SQLPORT,
            'charset' => 'utf8'
        ]
    ],
    'version_order' => 'creation'
];
