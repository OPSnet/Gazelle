<?php
authorize();

$reason = trim($_POST['reason']);
if (!$reason) {
    error('You must enter a reason!');
}

$CollageID = (int)$_POST['collageid'];
if (!$CollageID) {
    error(404);
}
$collage = new Gazelle\Collage($CollageID);

if (!$Viewer->permitted('site_collages_delete') && !$collage->isOwner($Viewer->id())) {
    error(403);
}

$collage->remove($Viewer, new Gazelle\Manager\Subscription, new Gazelle\Log, $reason);
header('Location: collages.php');
