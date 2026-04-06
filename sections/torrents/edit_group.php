<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error("You are not allowed to edit torrents");
}
$tgroup = new Manager\TGroup()->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}
$torMan = new Manager\Torrent();

echo $Twig->render('tgroup/edit.twig', [
    'body'         => new Util\Textarea('body', $tgroup->description(), 80, 20),
    'empty'        => $tgroup->torrentIdList() === [],
    'leech_reason' => $torMan->leechReasonList(),
    'leech_type'   => $torMan->leechTypeList(),
    'release_type' => new ReleaseType()->list(),
    'size'         => NEUTRAL_LEECH_THRESHOLD,
    'tgroup'       => $tgroup->showFallbackImage(false),
    'unit'         => NEUTRAL_LEECH_UNIT,
    'viewer'       => $Viewer,
]);
