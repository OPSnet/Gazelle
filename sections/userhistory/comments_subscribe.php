<?php
// perform the back end of subscribing to topics
authorize();

if (!in_array($_GET['page'], ['artist', 'collages', 'requests', 'torrents']) || !is_number($_GET['pageid'])) {
    error(0);
}

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);
$subscription->subscribeComments($_GET['page'], $_GET['pageid']);
