<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tagMan = new Gazelle\Manager\Tag();

$unofficialName = [];
if ($_POST['oldtags'] ?? null) {
    authorize();
    $unofficialId = [];
    foreach ($_POST['oldtags'] as $tagId) {
        $tag = $tagMan->findById($tagId);
        if (is_null($tag)) {
            error(403);
        }
        $unofficialId[]   = $tag->id();
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
    'auth'       => $Viewer->auth(),
    'list'       => $tagMan->officialList($_GET['order'] ?? 'name'),
    'new'        => $new,
    'unofficial' => $unofficialName,
]);
