<?php
/** @phpstan-var \Gazelle\User $Viewer */

// perform the back end of subscribing to topics

if ($Viewer->disableForums()) {
    error(403);
}
authorize();

$thread = (new Gazelle\Manager\ForumThread())->findById((int)($_GET['threadid'] ?? 0));
if (is_null($thread)) {
    error(404);
}
if (!$Viewer->readAccess($thread->forum())) {
    error(403);
}

json_print('success', (new Gazelle\User\Subscription($Viewer))->subscribe($thread->id()));
