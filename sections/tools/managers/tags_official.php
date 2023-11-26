<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tagMan = new Gazelle\Manager\Tag;

$unofficialName = [];
if ($_POST['oldtags'] ?? null) {
    authorize();
    $unofficialId = [];
    foreach ($_POST['oldtags'] as $tagId) {
        $name = $tagMan->name($tagId);
        if (is_null($name)) {
            error(403);
        }
        $unofficialId[]   = (int)$tagId;
        $unofficialName[] = $name;
    }
    $tagMan->unofficialize($unofficialId);
}

$new = false;
if ($_POST['newtag'] ?? null) {
    authorize();
    $id = $tagMan->officialize($_POST['newtag'], $Viewer);
    if ($id) {
        $new = $tagMan->findById($id);
    }
}

echo $Twig->render('tag/official.twig', [
    'auth'       => $Viewer->auth(),
    'list'       => $tagMan->officialList($_GET['order'] ?? 'name'),
    'new'        => $new,
    'unofficial' => $unofficialName,
]);
