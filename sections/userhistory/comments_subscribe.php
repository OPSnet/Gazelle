<?php

if (!in_array($_GET['page'], ['artist', 'collages', 'requests', 'torrents']) || !(int)($_GET['pageid'] ?? 0)) {
    error(0);
}
authorize();

(new Gazelle\Subscription($Viewer))->subscribeComments($_GET['page'], (int)$_GET['pageid']);
