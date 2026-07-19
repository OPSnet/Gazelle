<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$notifier = new User\Notification\Collage($Viewer);
if (!isset($_REQUEST['collageid'])) {
    $notifier->clear();
} else {
    $collage = new Manager\Collage()->findById((int)$_REQUEST['collageid']);
    if (is_null($collage)) {
        Error404::error();
    }
    $notifier->clearCollage($collage);
}

header('Location: userhistory.php?action=subscribed_collages');
