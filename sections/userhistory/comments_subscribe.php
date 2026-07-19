<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!in_array($_GET['page'], ['artist', 'collages', 'requests', 'torrents']) || !(int)($_GET['pageid'] ?? 0)) {
    Error400::error('Unknown comments subscription target');
}
authorize();

new User\Subscription($Viewer)->subscribeComments($_GET['page'], (int)$_GET['pageid']);
