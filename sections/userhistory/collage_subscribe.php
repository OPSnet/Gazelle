<?php
// perform the back end of subscribing to collages
authorize();

$collageId = (int)$_GET['collageid'];
if (!$collageId) {
    error(404);
}

$subMan = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
$subMan->toggleCollageSubscription($collageId);
