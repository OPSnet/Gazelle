<?php
authorize();

$subMan = new \Gazelle\Manager\Subscription($Viewer->id());
$subMan->catchupSubscriptions();

header('Location: userhistory.php?action=subscriptions');
