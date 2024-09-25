<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!in_array($_GET['page'], ['artist', 'collages', 'requests', 'torrents']) || !(int)($_GET['pageid'] ?? 0)) {
    error('Unknown comments subscription target');
}
authorize();

(new Gazelle\User\Subscription($Viewer))->subscribeComments($_GET['page'], (int)$_GET['pageid']);
