<?php

if (!$Viewer->permitted('admin_site_debug')) {
    error(403);
}

$proc = [];
if (preg_match('/.*\/(.*)/', PHP_BINARY, $match, PREG_UNMATCHED_AS_NULL)) {
    $binary = $match[1] ?? 'php-fpm';
    $ps = trim(`ps -C ${binary} -o pid --no-header`);
    $pidList = explode("\n", $ps);
    foreach ($pidList as $pid) {
        $p = $Cache->get_value("php_$pid");
        if ($p !== false) {
            $proc[$pid] = $p;
        }
    }
}

echo $Twig->render('admin/process-list.twig', [
    'proc' => $proc,
    'now'  => date('Y-m-d H:i:s'),
]);
