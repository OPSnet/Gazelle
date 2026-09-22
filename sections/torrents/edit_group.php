<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error("You are not allowed to edit torrents");
}
$tgroup = new Manager\TGroup()->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}

echo $Twig->render('tgroup/edit.twig', [
    'body'         => new Util\Textarea('body', $tgroup->description(), 80, 20),
    'empty'        => $tgroup->torrentIdList() === []
        && array_filter(
            new Manager\Request()->findByTGroup($tgroup),
            fn ($r) => !$r->isFilled()
        ) === [],
    'release_type' => $Viewer->releaseOrderLabel(new ReleaseType()->list()),
    'tgroup'       => $tgroup,
    'viewer'       => $Viewer,
]);
