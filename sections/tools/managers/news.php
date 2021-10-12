<?php

use Gazelle\Manager\Notification;

if (!$Viewer->permitted('admin_manage_news')) {
    error(403);
}

$newsMan = new Gazelle\Manager\News;
$create = false;
switch ($_REQUEST['action']) {
    case 'takenewnews':
        $newsMan->create($Viewer->id(), $_POST['title'], $_POST['body']);
        $notification = new Notification($Viewer->id());
        $notification->push($notification->pushableUsers(), $_POST['title'], $_POST['body'], SITE_URL . '/index.php', Notification::NEWS);
        header('Location: index.php');
        exit;
        break;

    case 'takeeditnews':
        $id = (int)$_REQUEST['id'];
        if ($id < 1) {
            error(0);
        }
        authorize();
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
        $title = display_str($title);
        $body = display_str($body);
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
View::show_header('Manage news', ['js' => 'bbcode,news_ajax']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $create ? 'Create a news post' : 'Edit news post';?></h2>
    </div>
    <form class="<?= $create ? 'create_form' : 'edit_form';?>" name="news_post" action="tools.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="<?= $create ? 'takenewnews' : 'takeeditnews';?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<?php if ($_GET['action'] == 'editnews') { ?>
            <input type="hidden" name="id" value="<?= $id ?>" />
<?php } ?>
            <h3>Title</h3>
            <input type="text" name="title" size="95"<?= empty($title) ? '' : ' value="'. $title . '"' ?> />
            <br />
            <h3>Body</h3>
            <textarea name="body" cols="95" rows="15"><?= empty($body) ? '' : $body ?></textarea><br /><br />
            <div class="center">
                <input type="submit" value="<?= $create ? 'Create news post' : 'Edit news post';?>" />
            </div>
        </div>
    </form>
<?php if ($_GET['action'] != 'editnews') { ?>
    <h2>News archive</h2>
<?php
$headlines = $newsMan->headlines();
foreach ($headlines as $article) {
    [$id, $title, $body, $time] = $article;
?>
    <div class="box vertical_space news_post">
        <div class="head">
            <strong><?= display_str($title) ?></strong> - posted <?=time_diff($time) ?>
            - <a href="tools.php?action=editnews&amp;id=<?= $id ?>" class="brackets">Edit</a>
            <a href="tools.php?action=deletenews&amp;id=<?= $id ?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Delete</a>
        </div>
        <div class="pad"><?= Text::full_format($body) ?></div>
    </div>
<?php } ?>
    <div id="more_news" class="box">
        <div class="head">
            <em><span><a href="#" onclick="news_ajax(event, 3, <?=$NewsCount?>, 1, '<?= $Viewer->auth() ?>'); return false;">Click to load more news</a>.</span></em>
        </div>
    </div>
<?php } ?>
</div>
<?php

View::show_footer();
