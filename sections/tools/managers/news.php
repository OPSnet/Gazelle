<?php
enforce_login();
if (!check_perms('admin_manage_news')) {
    error(403);
}
$NewsCount = 5;
View::show_header('Manage news', 'bbcode,news_ajax');

switch ($_GET['action']) {
    case 'takeeditnews':
        if (!check_perms('admin_manage_news')) {
            error(403);
        }
        if (is_number($_POST['newsid'])) {
            authorize();
            $DB->prepared_query("
                UPDATE news SET
                    Title = ?
                    Body = ?
                WHERE ID = ?
                ", trim($_POST['title']), trim($_POST['body']), (int)$_POST['newsid']
            );
            $Cache->delete_value('news');
            $Cache->delete_value('feed_news');
        }
        header('Location: index.php');
        break;
    case 'editnews':
        if (is_number($_GET['id'])) {
            $NewsID = $_GET['id'];
            list($Title, $Body) = $DB->row("
                SELECT Title, Body
                FROM news
                WHERE ID = ?
                ", $NewsID
            );
        }
}
?>
<div class="thin">
    <div class="header">
        <h2><?= ($_GET['action'] == 'news') ? 'Create a news post' : 'Edit news post';?></h2>
    </div>
    <form class="<?= ($_GET['action'] == 'news') ? 'create_form' : 'edit_form';?>" name="news_post" action="tools.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="<?= ($_GET['action'] == 'news') ? 'takenewnews' : 'takeeditnews';?>" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php if ($_GET['action'] == 'editnews') { ?>
            <input type="hidden" name="newsid" value="<?=$NewsID; ?>" />
<?php } ?>
            <h3>Title</h3>
            <input type="text" name="title" size="95"<?php if (!empty($Title)) { echo ' value="'.display_str($Title).'"'; } ?> />
<!-- Why did someone add this?    <input type="datetime" name="datetime" value="<?=sqltime()?>" /> -->
            <br />
            <h3>Body</h3>
            <textarea name="body" cols="95" rows="15"><?php if (!empty($Body)) { echo display_str($Body); } ?></textarea> <br /><br />


            <div class="center">
                <input type="submit" value="<?= ($_GET['action'] == 'news') ? 'Create news post' : 'Edit news post';?>" />
            </div>
        </div>
    </form>
<?php if ($_GET['action'] != 'editnews') { ?>
    <h2>News archive</h2>
<?php
$DB->prepared_query('
    SELECT
        ID,
        Title,
        Body,
        Time
    FROM news
    ORDER BY Time DESC
    LIMIT ?
    ', $NewsCount // LIMIT 20
);
$Count = 0;
while (list($NewsID, $Title, $Body, $NewsTime) = $DB->next_record()) {
?>
    <div class="box vertical_space news_post">
        <div class="head">
            <strong><?=display_str($Title) ?></strong> - posted <?=time_diff($NewsTime) ?>
            - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>" class="brackets">Edit</a>
            <a href="tools.php?action=deletenews&amp;id=<?=$NewsID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Delete</a>
        </div>
        <div class="pad"><?=Text::full_format($Body) ?></div>
    </div>
<?php
    if (++$Count > ($NewsCount - 1)) {
        break;
    }
} ?>
    <div id="more_news" class="box">
        <div class="head">
            <em><span><a href="#" onclick="news_ajax(event, 3, <?=$NewsCount?>, 1, '<?=$LoggedUser['AuthKey']?>'); return false;">Click to load more news</a>.</span></em>
        </div>
    </div>
<?php } ?>
</div>
<?php

View::show_footer();
