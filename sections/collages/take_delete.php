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

if (!check_perms('site_collages_delete') && !$collage->isOwner($LoggedUser['ID'])) {
    error(403);
}

$collage->remove(new Gazelle\User($LoggedUser['ID']), new Gazelle\Manager\Subscription, new Gazelle\Log, $reason);
header('Location: collages.php');
