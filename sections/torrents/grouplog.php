<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$tgroup = new Manager\TGroup()->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    // we may not have a torrent group because it has already been merged elsewhere
    // so the best we can hope for is something that looks like a positive integer
    Error404::error();
}

echo $Twig->render('tgroup/group-log.twig', [
    'id'     => $_GET['id'] ?? '',
    'tgroup' => $tgroup,
    'log'    => new Manager\SiteLog()->tgroupLogList($tgroup),
]);
