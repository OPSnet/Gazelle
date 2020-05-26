<?php

/* A script to import the old WhatCD wiki articles from the 10th birthday release */

define('MEMORY_EXCEPTION', true);
define('TIME_EXCEPTION', true);
define('ERROR_EXCEPTION', true);

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');

use Gazelle\Util\Crypto;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

require('classes/proxies.class.php');

set_include_path(SERVER_ROOT);

require_once(SERVER_ROOT.'classes/time.class.php');
require_once(SERVER_ROOT.'classes/util.php');

$Debug = new DEBUG;
$Debug->handle_errors();
$Debug->set_flag('Debug constructed');

$DB = new DB_MYSQL;
$Cache = new CACHE;

$in = fopen($argv[1], 'r');

$row = fgets($in);
list(, $ID) = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
list(, $read) = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
list(, $edit) = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
list(, $date) = explode(':', str_replace(["\n", "\r"], '', fgets($in)), 2);
list(, $title) = explode(':', str_replace(["\n", "\r"], '', fgets($in)), 2);
list(, $body) = explode(':', fgets($in));

$title = '[WHAT.CD] ' . trim($title);
$body = trim($body);

echo "$title\n";

while(($row = fgets($in))) {
    $body .= $row;
}

$DB->prepared_query('
	INSERT INTO wiki_articles (Title, Body, MinClassRead, MinClassEdit, Date, Author)
	VALUES (?, ?, 800, 800, now(), 2)
	', $title, $body);
