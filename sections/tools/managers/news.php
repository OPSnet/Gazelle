<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Manager\Notification;

if (!$Viewer->permitted('admin_manage_news')) {
    error(403);
}

$newsMan = new Gazelle\Manager\News();
$create  = false;
$title   = '';
$body    = '';
$id      = false;

switch ($_REQUEST['action']) {
    case 'takenewnews':
        $newsMan->create($Viewer, $_POST['title'], $_POST['body']);
        $notification = new Notification();
        $notification->push($notification->pushableTokens(Gazelle\Enum\NotificationType::NEWS), $_POST['title'], $_POST['body'], SITE_URL . '/index.php');
        header('Location: index.php');
        exit;

    case 'takeeditnews':
        authorize();
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            error('Unknown id for handle news item edit');
        }
        $newsMan->modify($id, $_POST['title'], $_POST['body']);
        header('Location: index.php');
        exit;

    case 'editnews':
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            error('Unknown id for news item edit');
        }
        [$title, $body] = $newsMan->fetch($id);
        break;

    case 'deletenews':
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            error('Unknown id for news item delete');
        }
        $newsMan->remove($id);
        header('Location: index.php');
        exit;

    case 'news':
        $create = true;
        break;

    default:
        error('Unknown news action');
}
echo $Twig->render('admin/news.twig', [
    'auth'    => $Viewer->auth(),
    'body'    => new Gazelle\Util\Textarea('body', $body),
    'create'  => $create,
    'id'      => $id,
    'title'   => $title,
    'list'    => $newsMan->headlines(),
]);
