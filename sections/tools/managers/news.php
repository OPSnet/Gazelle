<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Manager\Notification;
use Gazelle\Enum\NotificationType;

if (!$Viewer->permitted('admin_manage_news')) {
    Error403::error();
}

$newsMan = new Manager\News();
$create  = false;
$title   = '';
$body    = '';
$id      = false;

switch ($_REQUEST['action']) {
    case 'takenewnews':
        $newsMan->create(
            $_POST['title'],
            $_POST['body'],
            trim($_POST['pitch'] ?? '') ?: 'Discuss this post',
            $Viewer,
            new Manager\Forum()->findById(ANNOUNCEMENT_FORUM_ID),
        );
        $notification = new Notification();
        $notification->push($notification->pushableTokens(NotificationType::NEWS), $_POST['title'], $_POST['body'], SITE_URL . '/index.php');
        header('Location: index.php');
        return;

    case 'takeeditnews':
        authorize();
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            Error400::error('Unknown id for handle news item edit');
        }
        $newsMan->modify($id, $_POST['title'], $_POST['body']);
        header('Location: index.php');
        return;

    case 'editnews':
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            Error400::error('Unknown id for news item edit');
        }
        [$title, $body] = $newsMan->fetch($id);
        break;

    case 'deletenews':
        $id = (int)$_REQUEST['id'];
        if (!$id) {
            Error400::error('Unknown id for news item delete');
        }
        $newsMan->remove($id);
        header('Location: index.php');
        return;

    case 'news':
        $create = true;
        break;

    default:
        Error400::error('Unknown news action');
}
echo $Twig->render('admin/news.twig', [
    'body'   => new Util\Textarea('body', $body),
    'create' => $create,
    'id'     => $id,
    'title'  => $title,
    'list'   => $newsMan->headlines(),
    'viewer' => $Viewer,
]);
