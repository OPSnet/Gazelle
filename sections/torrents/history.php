<?php

$group = (new Gazelle\Manager\TGroup)->findById((int)($_GET['id'] ?? 0));
if (is_null($group)) {
    error(404);
}

echo $Twig->render('revision.twig', ['object' => $group]);
