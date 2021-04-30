<?php

/* A script to import the old WhatCD wiki articles from the 10th birthday release */

define('MEMORY_EXCEPTION', true);
define('TIME_EXCEPTION', true);
define('ERROR_EXCEPTION', true);

require_once(__DIR__ . '/../classes/config.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../classes/util.php');


use Gazelle\Util\Crypto;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

set_include_path(SERVER_ROOT);

$Cache = new CACHE;
$DB    = new DB_MYSQL;
$Debug = new Gazelle\Debug($Cache, $DB);
$Debug->handle_errors();
$Debug->set_flag('Debug constructed');

$in = fopen($argv[1], 'r');

$row = fgets($in);
[, $ID] = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
[, $read] = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
[, $edit] = explode(':', str_replace(["\n", "\r"], '', fgets($in)));
[, $date] = explode(':', str_replace(["\n", "\r"], '', fgets($in)), 2);
[, $title] = explode(':', str_replace(["\n", "\r"], '', fgets($in)), 2);
[, $body] = explode(':', fgets($in));

$title = '[WHAT.CD] ' . trim($title);
$body = trim($body);

echo "$title\n";

while(($row = fgets($in))) {
    $body .= $row;
}

$DB->prepared_query('
    INSERT INTO wiki_articles (Title, Body, MinClassRead, MinClassEdit, Date, Author)
    VALUES (?, ?, 800, 800, now(), 2)
    ', $title, $body
);
