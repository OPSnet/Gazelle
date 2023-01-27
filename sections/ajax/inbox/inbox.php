<?php

$json = new Gazelle\Json\Inbox(
    $Viewer,
    $_GET['type'] ?? 'inbox',
    (int)($_GET['page'] ?? 1),
    ($_GET['sort'] ?? 'unread') === 'unread',
    new Gazelle\Manager\User,
);

if (!empty($_GET['search'])) {
    $json->setSearch($_GET['searchtype'] ?? 'subject', $_GET['search']);
}

$json->setVersion(1)->emit();
