<?php

if (!$Viewer->permitted('users_view_ips')) {
    echo 'Forbidden';
    exit;
}

if (isset($_SERVER['http_if_modified_since'])) {
    header('Status: 304 Not Modified');
    exit;
}

$ip = trim($_GET['ip'] ?? '');
$hostname = gethostbyaddr($ip);
if ($hostname === false) {
    header('Expires: ' . date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); // 120 days
    header('Last-Modified: ' . date('D, d-M-Y H:i:s \U\T\C', time()));
}
header('Content-Type: application/json; charset=text/plain');
echo json_encode(['ip' => $ip, 'hostname' => $hostname]);
