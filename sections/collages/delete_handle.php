<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$reason = trim($_POST['reason']);
if (!$reason) {
    Error400::error('You must enter a reason!');
}

$collage = new Manager\Collage()->findById((int)$_POST['collageid']);
if (is_null($collage)) {
    Error404::error();
}
if (!$Viewer->permitted('site_collages_delete') && !$collage->isOwner($Viewer)) {
    Error403::error();
}

$collageId = $collage->id;
$name = $collage->name();
$collage->remove();

new Manager\Subscription()->flushPage('collages', $collageId);
$collage->logger()->general(
   "Collage $collageId ($name) was deleted by {$Viewer->username()}: $reason"
);

header('Location: collages.php');
