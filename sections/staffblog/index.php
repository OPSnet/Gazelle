<?php

use Gazelle\Util\Irc;

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$blogMan = new Gazelle\Manager\StaffBlog;
$blogMan->catchup($Viewer);

if ($Viewer->permitted('admin_manage_blog')) {
    $blog = $blogMan->findById((int)($_REQUEST['id'] ?? 0));
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'deleteblog':
                $blog->remove();
                header('Location: staffblog.php');
                exit;

            case 'editblog':
                // we have a blog to edit
                break;

            case 'takeeditblog':
            case 'takenewblog':
                authorize();
                $title = trim($_POST['title'] ?? '');
                if (empty($title)) {
                    error("Please enter a title.");
                }
                $body = trim($_POST['body'] ?? '');
                if (empty($body)) {
                    error("Please enter a body.");
                }
                if ($_REQUEST['action'] == 'takenewblog') {
                    $blog = $blogMan->create($Viewer, $title, $body);
                    Irc::sendMessage(MOD_CHAN, "New staff blog: " . $blog->title()
                        . " - " . $blog->publicLocation()
                    );
                } else {
                    $blog->setField('Title', $title)
                        ->setField('Body', $body)
                        ->modify();
                }
                header('Location: staffblog.php');
                exit;

           default:
                error(403);
        }
    }
}

View::show_header('Staff Blog', ['js' => 'bbcode']);

if (in_array($_REQUEST['action'] ?? '', ['', 'editblog'])) {
    echo $Twig->render('staffblog/edit.twig', [
        'action'    => empty($_REQUEST['action']) ? 'create' : 'edit',
        'auth'      => $Viewer->auth(),
        'blog'      => $blog ?? null,
        'show_form' => !isset($_REQUEST['action']) || $_REQUEST['action'] !== 'editblog',
    ]);
}

echo $Twig->render('staffblog/list.twig', [
    'list'   => $blogMan->blogList(),
    'viewer' => $Viewer,
]);
