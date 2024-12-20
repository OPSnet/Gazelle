<?php
/** @phpstan-var \Gazelle\User $Viewer */

$user = (new Gazelle\Manager\User())->findById((int)$_GET['id']);
if (is_null($user)) {
    json_die("failure", "bad id parameter");
}

echo (new Gazelle\Json\User($user, $Viewer))
    ->setVersion(2)
    ->response();
