<?php

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_GET['id']);
if (is_null($tgroup)) {
    error(404);
}

echo $Twig->render('tgroup/edit.twig', [
    'body'         => new Gazelle\Util\Textarea('body', $tgroup->description(), 80, 20),
    'release_type' => (new Gazelle\ReleaseType)->list(),
    'tgroup'       => $tgroup->showFallbackImage(false),
    'viewer'       => $Viewer,
]);
