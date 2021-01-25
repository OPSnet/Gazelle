<?php

$user = (new Gazelle\Manager\User)->findByUsername($_GET['search'] ?? $_GET['username'] ?? '');
if (!is_null($user)) {
    header("Location: user.php?id={$user->id()}");
}
error("There is no-one here with that name.");
