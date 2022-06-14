<?php

if (!$Viewer->permitted('users_view_ips')) {
    die('Access denied.');
}

if (isset($_SERVER['http_if_modified_since'])) {
    header('Status: 304 Not Modified');
    exit;
}

header('Expires: '.date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); //120 days
header('Last-Modified: '.date('D, d-M-Y H:i:s \U\T\C', time()));

if (empty($_GET['ip'])) {
    die('Invalid IP address.');
}

die(geoip($_GET['ip']));
