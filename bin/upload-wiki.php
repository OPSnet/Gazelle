<?php

/* A script to import the old WhatCD wiki articles from the 10th birthday release */

require_once(__DIR__ . '/../lib/bootstrap.php');

$in = fopen($argv[1], 'r');
if ($in === false) {
    exit(1);
}

$row = (string)fgets($in);
[, $ID] = explode(':', str_replace(["\n", "\r"], '', (string)fgets($in)));
[, $read] = explode(':', str_replace(["\n", "\r"], '', (string)fgets($in)));
[, $edit] = explode(':', str_replace(["\n", "\r"], '', (string)fgets($in)));
[, $date] = explode(':', str_replace(["\n", "\r"], '', (string)fgets($in)), 2);
[, $title] = explode(':', str_replace(["\n", "\r"], '', (string)fgets($in)), 2);
[, $body] = explode(':', (string)fgets($in));

$title = '[WHAT.CD] ' . trim($title);
$body = trim($body);

echo "$title\n";

while (($row = fgets($in))) {
    $body .= $row;
}

Gazelle\DB::DB()->prepared_query('
    INSERT INTO wiki_articles (Title, Body, MinClassRead, MinClassEdit, Date, Author)
    VALUES (?, ?, 800, 800, now(), 2)
    ', $title, $body
);
