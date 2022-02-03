<?php

if (isset($_SERVER['http_if_modified_since'])) {
    header('Status: 304 Not Modified');
    die();
}

header('Expires: '.date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); //120 days
header('Last-Modified: '.date('D, d-M-Y H:i:s \U\T\C', time()));

if (!$Viewer->permitted('users_view_ips')) {
    die('Access denied.');
}
if ($_GET['ip'] != long2ip(ip2long($_GET['ip']))) {
    die('Invalid IPv4 address.');
}

$Output = explode(' ', shell_exec('host -W 1 ' . escapeshellarg($_GET['ip'])));
if (count($Output) == 1 && empty($Output[0])) {
    trigger_error('no output received: ensure that "host -W" functions correctly');
} elseif (count($Output) != 5) {
    print 'Could not retrieve host.';
} else {
    echo trim($Output[4]);
}
