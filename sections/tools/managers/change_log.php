<?php

$changeMan = new Gazelle\Manager\Changelog;

if ($Viewer->permitted('users_mod') && isset($_POST['perform'])) {
    authorize();
    switch ($_POST['perform']) {
        case 'add':
            if (!empty($_POST['message'])) {
                $changeMan->create($_POST['message'], $_POST['author']);
            }
            break;
        case 'remove':
            if ($_POST['change_id']) {
                $changeMan->remove((int)$_POST['change_id']);
            }
            break;
        default:
            error(403);
    }
}

$paginator = new Gazelle\Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($changeMan->total());

echo $Twig->render('admin/changelog.twig', [
    'list'      => $changeMan->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
