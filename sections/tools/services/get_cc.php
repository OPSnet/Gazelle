<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    die('Access denied.');
}

if (isset($_SERVER['http_if_modified_since'])) {
    header('Status: 304 Not Modified');
    exit;
}

header('Expires: ' . date('D, d-M-Y H:i:s \U\T\C', time() + 3600 * 24 * 120)); //120 days
header('Last-Modified: ' . date('D, d-M-Y H:i:s \U\T\C', time()));
echo new Search\ASN()->countryISO($_GET['ip']);
