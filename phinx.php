<?php

require(__DIR__.'/classes/config.php');

return
[
	'paths' => [
		'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
    	'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
	],
	'environments' => [
		'default_migration_table' => 'phinxlog',
		'default_database' => 'gazelle',
		'orpheus' => [
			'adapter' => 'mysql',
			'host' => SQLHOST,
			'name' => SQLDB,
			'user' => SQLLOGIN,
			'pass' => SQLPASS,
			'port' => SQLPORT,
			'charset' => 'utf8'
		],
		'gazelle' => [
			'adapter' => 'mysql',
			'host' => SQLHOST,
			'name' => SQLDB,
			'user' => SQLLOGIN,
			'pass' => SQLPASS,
			'port' => SQLPORT,
			'charset' => 'utf8'
		],
		'vagrant' => [
			'adapter' => 'mysql',
			'host' => 'localhost',
			'name' => 'gazelle',
			'user' => 'gazelle',
			'pass' => 'password',
			'port' => 3306,
			'charset' => 'utf8'
		],
		'vagrant_external' => [
			'adapter' => 'mysql',
			'host' => '127.0.0.1',
			'name' => 'gazelle',
			'user' => 'gazelle',
			'pass' => 'password',
			'port' => 36000,
			'charset' => 'utf8'
		]
	],
	'version_order' => 'creation'
];
/*
paths:
    migrations: '%%PHINX_CONFIG_DIR%%/db/migrations'
    seeds: '%%PHINX_CONFIG_DIR%%/db/seeds'

environments:
  default_migration_table: phinxlog
  default_database: vagrant
  production:
    adapter: mysql
    host: localhost
    name: production_db
    user: root
    pass: ''
    port: 3306
    charset: utf8

  vagrant:
    adapter: mysql
    host: localhost
    name: gazelle
    user: gazelle
    pass: password
    port: 3306
    charset: utf8

  vagrant_remote:
    adapter: mysql
    host: localhost
    name: gazelle
    user: gazelle
    pass: password
    port: 36000
    charset: utf8

version_order: creation
*/
