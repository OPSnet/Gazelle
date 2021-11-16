<?php

$json = new Gazelle\Json\Inbox;

if (!empty($_GET['search'])) {
    $json->setSearch($_GET['searchtype'] ?? 'subject', $_GET['search']);
}

$json->setVersion(1)
    ->setPage((int)($_GET['page'] ?? 1))
    ->setUnreadFirst(($_GET['sort'] ?? 'unread') === 'unread')
    ->setFolder($_GET['type'] ?? 'inbox')
    ->setViewer($Viewer)
    ->emit();
