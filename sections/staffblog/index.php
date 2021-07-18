<?php

use Gazelle\Util\Irc;

enforce_login();

if (!check_perms('users_mod')) {
    error(403);
}

$blogMan = new Gazelle\Manager\StaffBlog;
$blogMan->visit($Viewer->id());

View::show_header('Staff Blog', ['js' => 'bbcode']);

if (check_perms('admin_manage_blog')) {
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'editblog':
                if ((int)$_GET['id']) {
                    $blogMan->load((int)$_GET['id']);
                }
                break;

            case 'takeeditblog':
                authorize();
                $title = trim($_POST['title'] ?? '');
                if (empty($title)) {
                    error("Please enter a title.");
                }
                $body = trim($_POST['body'] ?? '');
                if (empty($body)) {
                    error("Please enter a body.");
                }
                if ((int)$_POST['blogid']) {
                    $blogMan->setId((int)$_POST['blogid'])
                        ->setTitle(trim($_POST['title']))
                        ->setBody(trim($_POST['body']))
                        ->modify();
                }
                header('Location: staffblog.php');
                exit;

            case 'deleteblog':
                if ((int)$_GET['id']) {
                    authorize();
                    $blogMan->remove((int)$_GET['id']);
                }
                header('Location: staffblog.php');
                exit;

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
                $blogMan->setTitle($title)
                    ->setBody($body)
                    ->setAuthorId($Viewer->id())
                    ->modify();
                Irc::sendRaw("PRIVMSG ".MOD_CHAN." :New staff blog: " . $blogMan->title()
                    . " - " . SITE_URL."/staffblog.php#blog" . $blogMan->blogId()
                );
                header('Location: staffblog.php');
                exit;

           default:
                error(403);
                break;
        }
    }
    echo $Twig->render('staffblog/edit.twig', [
        'auth' => $Viewer->auth(),
        'blog' => $blogMan,
        'verb' => empty($_GET['action']) ? 'create' : 'edit',
        'show_form' => !isset($_REQUEST['action']) || $_REQUEST['action'] != 'editblog',
    ]);
}

echo $Twig->render('staffblog/list.twig', [
    'auth'   => $Viewer->auth(),
    'editor' => check_perms('admin_manage_blog'),
    'list'   => $blogMan->blogList(),
]);

View::show_footer();
