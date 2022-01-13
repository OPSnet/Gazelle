<?php

use Gazelle\Manager\Notification;

if (!$Viewer->permitted('admin_manage_news')) {
    error(403);
}

$newsMan = new Gazelle\Manager\News;
$create  = false;
$title   = '';
$body    = '';
$id      = false;

switch ($_REQUEST['action']) {
    case 'takenewnews':
        $newsMan->create($Viewer->id(), $_POST['title'], $_POST['body']);
        $notification = new Notification($Viewer->id());
        $notification->push($notification->pushableUsers($Viewer->id()), $_POST['title'], $_POST['body'], SITE_URL . '/index.php', Notification::NEWS);
        header('Location: index.php');
        exit;
        break;

    case 'takeeditnews':
        authorize();
        $id = (int)$_REQUEST['id'];
        if ($id < 1) {
            error(0);
        }
        $newsMan->modify($id, $_POST['title'], $_POST['body']);
        header('Location: index.php');
        exit;
        break;

    case 'editnews':
        $id = (int)$_REQUEST['id'];
        if ($id < 1) {
            error(0);
        }
        [$title, $body] = $newsMan->fetch($id);
        break;

    case 'deletenews':
        $id = (int)$_REQUEST['id'];
        if ($id < 1) {
            error(0);
        }
        $newsMan->remove($id);
        header('Location: index.php');
        exit;
        break;

    case 'news':
        $create = true;
        break;

    default:
        error(0);
        break;
}
echo $Twig->render('admin/news.twig', [
    'auth'    => $Viewer->auth(),
    'body'    => new Gazelle\Util\Textarea('body', $body),
    'create'  => $create,
    'id'      => $id,
    'title'   => $title,
    'list'    => $newsMan->headlines(),
]);
