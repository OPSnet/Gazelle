<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$tagMan = new Manager\Tag();

$unofficialName = [];
if ($_POST['oldtags'] ?? null) {
    authorize();
    $unofficialId = [];
    foreach ($_POST['oldtags'] as $tagId) {
        $tag = $tagMan->findById($tagId);
        if (is_null($tag)) {
            Error403::error();
        }
        $unofficialId[]   = $tag->id;
        $unofficialName[] = $tag->name();
    }
    $tagMan->unofficialize($unofficialId);
}

$new = false;
if ($_POST['newtag'] ?? null) {
    authorize();
    $new = $tagMan->officialize($_POST['newtag'], $Viewer);
}

echo $Twig->render('tag/official.twig', [
    'list'       => $tagMan->officialList($_GET['order'] ?? 'name'),
    'new'        => $new,
    'unofficial' => $unofficialName,
    'viewer'     => $Viewer,
]);
