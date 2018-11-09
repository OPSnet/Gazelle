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
		'gazelle' => [
			'adapter' => 'mysql',
			'host' => SQLHOST,
			'name' => SQLDB,
			'user' => SQLLOGIN,
			'pass' => SQLPASS,
			'port' => SQLPORT,
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
