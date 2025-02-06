<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

$reason = trim($_POST['reason']);
if (!$reason) {
    error('You must enter a reason!');
}

$collage = (new Gazelle\Manager\Collage())->findById((int)$_POST['collageid']);
if (is_null($collage)) {
    error(404);
}
if (!$Viewer->permitted('site_collages_delete') && !$collage->isOwner($Viewer)) {
    error(403);
}

$collageId = $collage->id();
$name = $collage->name();
$collage->remove();

(new Gazelle\Manager\Subscription())->flushPage('collages', $collageId);
$collage->logger()->general(
   "Collage $collageId ($name) was deleted by {$Viewer->username()}: $reason"
);

header('Location: collages.php');
