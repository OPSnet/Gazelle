<?php

require __DIR__ . '/../lib/config.php';

/* This second batch of Mysql migrations are run after the Postgresql migrations
 * have been run. This is needed for the CI, to drop tables in the Mysql schema
 * after they have been relayed to Postgresql. Otherwise they would be dropped
 * before Pg has the chance to do anything about them (create a foreign table
 * and/or migrate contents).
 */

return [
    'paths' => [
        'migrations' => __DIR__ . '/my2-migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog2',
        'default_environment'     => 'my2',
        'my2' => [
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
