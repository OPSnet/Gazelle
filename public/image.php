<?php

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

define('SKIP_NO_CACHE_HEADERS', 1);
header('Expires: '.date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); // 120 days
header('Last-Modified: '.date('D, d-M-Y H:i:s \U\T\C', time()));

require_once(__DIR__ . '/../classes/script_start.php');
