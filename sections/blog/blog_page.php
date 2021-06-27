<?php

View::show_header('Blog','bbcode');

$action = !empty($_GET['action']) && $_GET['action'] === 'editblog' ? 'Edit' : 'Create';
if (check_perms('admin_manage_blog')) {
    $textarea = new Gazelle\Util\Textarea('body', $action === 'Edit' ? $blog->title() : '');
    if ($action === 'Edit' && !empty($_GET['id'])) {
        $blog = new Gazelle\Blog((int)$_GET['id']);
    }
?>
<div class="box thin">
    <div class="head"><?= $action ?> blog post</div>
    <form class="<?= $action === 'Create' ?  'create_form' : 'edit_form' ?>" name="blog_post" action="blog.php" method="post">
        <div class="pad">
            <input type="hidden" name="action" value="<?= $action === 'Create' ? 'takenewblog' : 'takeeditblog' ?>"/>
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>"/>
<?php if ($action === 'Edit') { ?>
                <input type="hidden" name="blogid" value="<?= $blog->id() ?>"/>
<?php } ?>
            <h3>Title</h3>
            <input type="text" name="title" size="95" value="<?= $action === 'Edit' ? $blog->title() : '' ?>" /><br/>

            <h3>Body</h3>
                <?= $textarea->preview() ?>
                <?= $textarea->field() ?>
            <br/>
            <input type="checkbox" value="1" name="important" id="important" checked="checked" />
            <label for="important">Important</label><br/>

            <h3>Thread ID</h3>
            <input type="text" name="thread" size="8" value="<?= $action === 'Edit' ? $blog->topicId() : '' ?>" />
            (Leave blank to create thread automatically, set to 0 to not use thread)
            <br/><br/>
            <input id="subscribebox" type="checkbox" name="subscribe"<?= $Viewer->option('AutoSubscribe') ? ' checked="checked"' : ''; ?> tabindex="2" />
            <label for="subscribebox">Subscribe</label>

            <div class="center">
                <?= $textarea->button() ?>
                <input type="submit" value="<?= $action ?> blog post" />
            </div>
        </div>
    </form>
</div>
<br/>
<?php
}

if ($action === 'Create') { /* default for non-staff */
?>
<div class="thin">
<?php
    $headlines = (new Gazelle\Manager\Blog)->headlines();
    if ($headlines) {
        if ((new \Gazelle\WitnessTable\UserReadBlog)->witness($Viewer->id())) {
            $Cache->delete_value('user_info_heavy_' . $Viewer->id());
        }
    }

    foreach ($headlines as $article) {
        [$BlogID, $Title, $Author, $AuthorID, $Body, $BlogTime, $ThreadID] = $article;
?>
    <div id="blog<?=$BlogID?>" class="box blog_post">
        <div class="head">
            <strong><?=$Title?></strong> - posted <?=time_diff($BlogTime);?> by <a href="user.php?id=<?=$AuthorID?>"><?=$Author?></a>
<?php    if (check_perms('admin_manage_blog')) { ?>
                - <a href="blog.php?action=editblog&amp;id=<?=$BlogID?>" class="brackets">Edit</a>
                <a href="blog.php?action=deleteblog&amp;id=<?=$BlogID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Delete</a>
<?php    } ?>
        </div>
        <div class="pad">
            <?= Text::full_format($Body) ?>
<?php    if ($ThreadID) { ?>
                <br /><br />
                <em><a href="forums.php?action=viewthread&amp;threadid=<?=$ThreadID?>">Discuss this post here</a></em>
<?php        if (check_perms('admin_manage_blog')) { ?>
                    <span style="float: right"><a href="blog.php?action=deadthread&amp;id=<?=$BlogID?>&amp;auth=<?= $Viewer->auth() ?>"
                        class="brackets">Remove link</a></span>
<?php
                }
            }
?>
        </div>
    </div>
    <br />
<?php
    }
}
?>
</div>
<?php
View::show_footer();
