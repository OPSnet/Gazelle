<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    Error403::error();
}
authorize();

$thread = new Manager\ForumThread()->findById((int)($_GET['threadid'] ?? 0));
if (is_null($thread)) {
    Error404::error();
}
if (!$Viewer->readAccess($thread->forum())) {
    Error403::error();
}

json_print('success', new User\Subscription($Viewer)->subscribe($thread));
