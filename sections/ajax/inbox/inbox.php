<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$json = new Json\Inbox(
    $Viewer,
    $_GET['type'] ?? 'inbox',
    (int)($_GET['page'] ?? 1),
    ($_GET['sort'] ?? 'unread') === 'unread',
);

if (!empty($_GET['search'])) {
    $json->setSearch($_GET['searchtype'] ?? 'subject', $_GET['search']);
}

echo $json->setVersion(1)->response();
