<?php

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    json_die("failure", "bad userid");
}

$json = (new Gazelle\Json\UserRecent)
    ->setManagerTGroup(new Gazelle\Manager\TGroup)
    ->setUser($user)
    ->setViewer($Viewer);

if (isset($_GET['limit'])) {
    $limit = (int)$_GET['limit'];
    if ($limit < 1 || $limit > 50) {
        json_die("failure", "bad limit");
    }
    $json->setLimit($limit);
}

$json->setVersion(2)->emit();
