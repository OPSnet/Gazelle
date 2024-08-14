<?php
/** @phpstan-var \Gazelle\User $Viewer */

$json = new Gazelle\Json\Inbox(
    $Viewer,
    $_GET['type'] ?? 'inbox',
    (int)($_GET['page'] ?? 1),
    ($_GET['sort'] ?? 'unread') === 'unread',
    new Gazelle\Manager\User(),
);

if (!empty($_GET['search'])) {
    $json->setSearch($_GET['searchtype'] ?? 'subject', $_GET['search']);
}

echo $json->setVersion(1)->response();
