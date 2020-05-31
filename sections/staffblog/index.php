<?php
enforce_login();

if (!check_perms('users_mod')) {
    error(403);
}

$DB->prepared_query("
    INSERT INTO staff_blog_visits
           (UserID)
    VALUES (?)
    ON DUPLICATE KEY UPDATE Time = NOW()
    ", $LoggedUser['ID']
);
$Cache->delete_value('staff_blog_read_'.$LoggedUser['ID']);

define('ANNOUNCEMENT_FORUM_ID', 5);

if (check_perms('admin_manage_blog')) {
    if (!empty($_REQUEST['action'])) {
        switch ($_REQUEST['action']) {
            case 'takeeditblog':
                authorize();
                if (empty($_POST['title'])) {
                    error("Please enter a title.");
                }
                if (is_number($_POST['blogid'])) {
                    $DB->query("
                        UPDATE staff_blog
                        SET Title = '".db_string($_POST['title'])."', Body = '".db_string($_POST['body'])."'
                        WHERE ID = '".db_string($_POST['blogid'])."'");
                    $Cache->delete_value('staff_blog');
                    $Cache->delete_value('staff_feed_blog');
                }
                header('Location: staffblog.php');
                break;
            case 'editblog':
                if (is_number($_GET['id'])) {
                    $BlogID = $_GET['id'];
                    $DB->query("
                        SELECT Title, Body
                        FROM staff_blog
                        WHERE ID = $BlogID");
                    list($Title, $Body, $ThreadID) = $DB->next_record();
                }
                break;
            case 'deleteblog':
                if (is_number($_GET['id'])) {
                    authorize();
                    $DB->query("
                        DELETE FROM staff_blog
                        WHERE ID = '".db_string($_GET['id'])."'");
                    $Cache->delete_value('staff_blog');
                    $Cache->delete_value('staff_feed_blog');
                }
                header('Location: staffblog.php');
                break;

            case 'takenewblog':
                authorize();
                if (empty($_POST['title'])) {
                    error("Please enter a title.");
                }
                $Title = db_string($_POST['title']);
                $Body = db_string($_POST['body']);

                $DB->prepared_query("
                    INSERT INTO staff_blog
                           (UserID, Title, Body)
                    VALUES (?,      ?,     ?)
                    ", $LoggedUser['ID'], trim($_POST['title']), trim($_POST['body'])
                );
                $Cache->delete_value('staff_blog');
                $Cache->delete_value('staff_blog_latest_time');

                send_irc("PRIVMSG ".MOD_CHAN." :!mod New staff blog: " . $_POST['title'] . " - https://".SSL_SITE_URL."/staffblog.php#blog" . $DB->inserted_id());

                header('Location: staffblog.php');
                break;
        }
    }
    View::show_header('Staff Blog','bbcode');
    ?>
        <div class="box box2 thin">
            <div class="head">
                <?=((empty($_GET['action'])) ? 'Create a staff blog post' : 'Edit staff blog post')?>
                <span style="float: right;">
                    <a href="#" onclick="$('#postform').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=((!isset($_REQUEST['action']) || $_REQUEST['action'] != 'editblog') ? 'Show' : 'Hide')?></a>
                </span>
            </div>
            <form class="<?=((empty($_GET['action'])) ? 'create_form' : 'edit_form')?>" name="blog_post" action="staffblog.php" method="post">
                <div id="postform" class="pad<?=(!isset($_REQUEST['action']) || $_REQUEST['action'] != 'editblog') ? ' hidden' : '' ?>">
                    <input type="hidden" name="action" value="<?=((empty($_GET['action'])) ? 'takenewblog' : 'takeeditblog')?>" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php   if (!empty($_GET['action']) && $_GET['action'] == 'editblog') { ?>
                    <input type="hidden" name="blogid" value="<?=$BlogID; ?>" />
<?php   } ?>
                    <div class="field_div">
                        <h3>Title</h3>
                        <input type="text" name="title" size="95"<?php if (!empty($Title)) { echo ' value="'.display_str($Title).'"'; } ?> />
                    </div>
                    <div class="field_div">
                        <h3>Body</h3>
                        <textarea name="body" cols="95" rows="15"><?php if (!empty($Body)) { echo display_str($Body); } ?></textarea> <br />
                    </div>
                    <div class="submit_div center">
                        <input type="submit" value="<?=((!isset($_GET['action'])) ? 'Create blog post' : 'Edit blog post') ?>" />
                    </div>
                </div>
            </form>
        </div>
<?php
} else {
    View::show_header('Staff Blog','bbcode');
}
?>
<div class="thin">
<?php
if (($Blog = $Cache->get_value('staff_blog')) === false) {
    $DB->query("
        SELECT
            b.ID,
            um.Username,
            b.Title,
            b.Body,
            b.Time
        FROM staff_blog AS b
            LEFT JOIN users_main AS um ON b.UserID = um.ID
        ORDER BY Time DESC");
    $Blog = $DB->to_array(false, MYSQLI_NUM);
    $Cache->cache_value('staff_blog', $Blog, 1209600);
}

foreach ($Blog as $BlogItem) {
    list($BlogID, $Author, $Title, $Body, $BlogTime) = $BlogItem;
    $BlogTime = strtotime($BlogTime);
?>
            <div id="blog<?=$BlogID?>" class="box box2 blog_post">
                <div class="head">
                    <strong><?=$Title?></strong> - posted <?=time_diff($BlogTime);?> by <?=$Author?>
<?php       if (check_perms('admin_manage_blog')) { ?>
                    - <a href="staffblog.php?action=editblog&amp;id=<?=$BlogID?>" class="brackets">Edit</a>
                    <a href="staffblog.php?action=deleteblog&amp;id=<?=$BlogID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Do you want to delete this?');" class="brackets">Delete</a>
<?php       } ?>
                </div>
                <div class="pad">
                    <?=Text::full_format($Body)?>
                </div>
            </div>
<?php
}
?>
</div>
<?php
View::show_footer();
?>
