<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Gazelle\Cache $Cache
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_site_debug')) {
    Error403::error();
}

if (($_GET['view'] ?? '') === 'histogram') {
    global $Debug;
    echo $Twig->render('admin/process-histogram.twig', [
        'duration' => $Debug->durationHistogram(),
        'memory'   => $Debug->memoryHistogram(),
    ]);
    exit;
}

$proc = [];
if (preg_match('/.*\/(.*)/', PHP_BINARY, $match, PREG_UNMATCHED_AS_NULL)) {
    $binary = $match[1];
    $ps = shell_exec("ps -C {$binary} -o pid --no-header");
    if ($ps !== false && !is_null($ps)) {
        $pidList = explode("\n", trim($ps));
        foreach ($pidList as $pid) {
            $p = $Cache->get_value("php_$pid");
            if ($p !== false) {
                $proc[$pid] = $p;
            }
        }
    }
}

echo $Twig->render('admin/process-list.twig', [
    'proc' => $proc,
    'now'  => date('Y-m-d H:i:s'),
]);
