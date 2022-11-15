<?php

$user = (new Gazelle\Manager\User)->findById((int)$_GET['id']);
if (is_null($user)) {
    json_die("failure", "bad id parameter");
}

(new Gazelle\Json\User($user, $Viewer))
    ->setVersion(2)
    ->emit();
