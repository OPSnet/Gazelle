<?php
// We keep torrent groups cached. However, the peer counts change often, so the solution is to not cache them for long, or to update them. Here is where we update them.

$userAuthorized = isset($Viewer) && $Viewer->permitted('admin_schedule');
if (($argv[1] ?? '') != SCHEDULE_KEY && !$userAuthorized) {
    error(403);
}

ignore_user_abort();
ini_set('max_execution_time', 300);
ob_end_flush();
gc_enable();

if ($userAuthorized) {
    echo $Twig->createTemplate("{{ header('Peer update') }} <pre>")->render();
}

[$updated, $skipped] = (new Gazelle\Manager\Torrent)->updatePeerlists();

global $Debug;
$message = sprintf("Updated %d keys, skipped %d keys in %.6fs (%d kB memory)\n",
    $updated, $skipped, microtime(true) - $Debug->startTime(), memory_get_usage(true) >> 10
);

if ($userAuthorized) {
    echo $Twig->createTemplate("</pre><div>{{ message }}</div> {{ footer('') }}")
        ->render(['message' => $message]);
} else {
    echo $message;
}
