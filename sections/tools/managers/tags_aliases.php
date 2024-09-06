<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!($Viewer->permittedAny('users_mod', 'site_tag_aliases_read'))) {
    error(403);
}

$isAdmin = $Viewer->permitted('users_mod');
$tagMan  = new Gazelle\Manager\Tag();
$action  = null;
$result  = null;

if ($isAdmin) {
    $merge = false;
    if (isset($_POST['newalias'])) {
        $action = 'addition';
        $result = $tagMan->createAlias($_POST['badtag'], $_POST['aliastag']);
        $merge  = true;
    }
    if (isset($_POST['changealias']) || isset($_POST['delete'])) {
        $aliasId = (int)$_POST['aliasid'];
        if ($_POST['save']) {
            $action = 'modification';
            $result = $tagMan->modifyAlias($aliasId, $_POST['badtag'], $_POST['aliastag']);
            $merge  = true;
        } elseif ($_POST['delete']) {
            $action = 'removal';
            $result = $tagMan->removeAlias($aliasId);
        }
    }
    if ($merge) {
        $bad = $tagMan->findByName($_POST['badtag']);
        if ($bad) {
            $good = $tagMan->softCreate($_POST['aliastag'], $Viewer);
            if ($good) {
                $tagMan->rename($bad, [$good], $Viewer);
            }
        }
    }
}

echo $Twig->render('admin/tag-alias.twig', [
    'action'   => $action,
    'is_admin' => $isAdmin,
    'list'     => $tagMan->listAlias(($_GET['order'] ?? 'badtags') === 'badtags'),
    'result'   => $result,
]);
