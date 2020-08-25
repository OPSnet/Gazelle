<?php
authorize();

$subMan = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
$subMan->catchupSubscriptions();

header('Location: userhistory.php?action=subscriptions');
