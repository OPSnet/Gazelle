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
    return;
}

echo $Twig->render('admin/process-list.twig', [
    'proc' => SiteInfo::processList(),
    'now'  => date('Y-m-d H:i:s'),
]);
