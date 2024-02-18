<?php

use Gazelle\Util\Time;

$search = new Gazelle\Search\Forum($Viewer);
$search->setSearchType($_GET['type'] ?? 'title')
    ->setSearchText(trim($_GET['search'] ?? ''));

// Searching for posts in a specific thread
$ThreadID = (int)($_GET['threadid'] ?? 0);
if (!$ThreadID) {
    $Title = " &rsaquo; &ldquo;" . display_str($search->searchText()) . "&rdquo;";
} else {
    $Title = $search->threadTitle($ThreadID);
    if (is_null($Title)) {
        // naughty naughty
        error(403);
    }
    $search->setSearchType('body');
    $Title = " &rsaquo; <a href=\"forums.php?action=viewthread&amp;threadid=$ThreadID\">$Title</a>";
    $search->setThreadId($ThreadID);
}

$userSearch = trim($_GET['user'] ?? '');
if (!empty($userSearch)) {
    $search->setAuthor($userSearch);
}

$threadCreatedBefore = $_GET['thread_created_before'] ?? '';
if (!empty($threadCreatedBefore)) {
    if (!Time::isValidDate($threadCreatedBefore)) {
        error("Incorrect topic created before date");
    }
    $search->setThreadCreatedBefore($threadCreatedBefore);
}
$threadCreatedAfter = $_GET['thread_created_after'] ?? '';
if (!empty($threadCreatedAfter)) {
    if (!Time::isValidDate($threadCreatedAfter)) {
        error("Incorrect topic created after date");
    }
    $search->setThreadCreatedAfter($threadCreatedAfter);
}

if ($search->isBodySearch()) {
    $postCreatedBefore = $_GET['post_created_before'] ?? '';
    if (!empty($postCreatedBefore)) {
        if (!Time::isValidDate($postCreatedBefore)) {
            error("Incorrect post created before date");
        }
        $search->setPostCreatedBefore($postCreatedBefore);
    }
    $postCreatedAfter = $_GET['post_created_after'] ?? '';
    if (!empty($postCreatedAfter)) {
        if (!Time::isValidDate($postCreatedAfter)) {
            error("Incorrect post created after date");
        }
        $search->setPostCreatedAfter($postCreatedAfter);
    }
}

// Has the user checked individual forums?
if (isset($_GET['forums']) && is_array($_GET['forums'])) {
    $search->setForumList($_GET['forums']);
}

$paginator = new Gazelle\Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->totalHits());

View::show_header('Forums › Search', ['js' => 'bbcode,forum_search,datetime_picker', 'css' => 'datetime_picker']);
?>
<div class="thin">
    <div class="header">
        <h2><a href="forums.php">Forums</a> &rsaquo; Search<?=$Title?></h2>
    </div>
    <form class="search_form" name="forums" action="" method="get">
        <input type="hidden" name="action" value="search" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr>
                <td><strong>Search for:</strong></td>
                <td>
                    <input type="search" name="search" size="70" value="<?= display_str($search->searchText()) ?>" />
                </td>
            </tr>
            <tr>
                <td><strong>Posted by:</strong></td>
                <td>
                    <input type="search" name="user" placeholder="Username" size="70" value="<?= display_str($search->authorName()) ?>" />
                </td>
            </tr>
            <tr>
                <td><strong>Topic created:</strong></td>
                <td>
                    After:
                    <input type="text" class="date_picker" name="thread_created_after" id="thread_created_after" value="<?= $threadCreatedAfter ?>" />
                    Before:
                    <input type="text" class="date_picker" name="thread_created_before" id="thread_created_before" value="<?= $threadCreatedBefore ?>" />
                </td>
            </tr>
<?php if (!$ThreadID) { ?>
            <tr>
                <td><strong>Search in:</strong></td>
                <td>
                    <input type="radio" name="type" id="type_title" value="title"<?php if (!$search->isBodySearch()) {
echo ' checked="checked"'; } ?> />
                    <label for="type_title">Titles</label>
                    <input type="radio" name="type" id="type_body" value="body"<?php if ($search->isBodySearch()) {
echo ' checked="checked"'; } ?> />
                    <label for="type_body">Post bodies</label>
                </td>
            </tr>
            <tr id="post_created_row" <?php if (!$search->isBodySearch()) {
echo "class='hidden'"; } ?>>
                <td><strong>Post created:</strong></td>
                <td>
                    After:
                    <input type="text" class="date_picker" name="post_created_after" id="post_created_after" value="<?= $postCreatedAfter ?? '' ?>" />
                    Before:
                    <input type="text" class="date_picker" name="post_created_before" id="post_created_before" value="<?= $postCreatedBefore ?? '' ?>" />
                </td>
            </tr>
            <tr>
                <td><strong>Forums:</strong></td>
                <td>
        <table id="forum_search_cat_list" class="cat_list layout">
<?php
    // List of forums
    $Open = false;
    $LastCategoryID = -1;
    $Columns = 0;
    $i = 0;
    $Forums = (new Gazelle\Manager\Forum())->forumList();
    foreach ($Forums as $forumId) {
        $forum = new Gazelle\Forum($forumId);
        if (!$Viewer->readAccess($forum)) {
            continue;
        }
        $Columns++;

        if ($forum->categoryId() != $LastCategoryID) {
            $LastCategoryID = $forum->categoryId();
            if ($Open) {
                if ($Columns % 5) {
?>
                <td colspan="<?=(5 - ($Columns % 5))?>"></td>
<?php           } ?>
            </tr>
<?php
            }
            $Columns = 0;
            $Open = true;
            $i++;
?>
            <tr>
                <td colspan="5" class="forum_cat">
                    <strong><?= $forum->categoryName() ?></strong>
                    <a href="#" class="brackets forum_category" id="forum_category_<?=$i?>">Check all</a>
                </td>
            </tr>
            <tr>
<?php   } elseif ($Columns % 5 == 0) { ?>
            </tr>
            <tr>
<?php   } ?>
                <td>
                    <input type="checkbox" name="forums[]" value="<?= $forumId ?>" data-category="forum_category_<?=$i?>" id="forum_<?= $forumId ?>"<?= in_array( $forumId, ($_GET['forums'] ?? [])) ? ' checked="checked"' : '' ?> />
                    <label for="forum_<?= $forumId ?>"><?=htmlspecialchars($forum->name())?></label>
                </td>
<?php
    }
    if ($Columns % 5) {
?>
                <td colspan="<?=(5 - ($Columns % 5))?>"></td>
<?php    } ?>
            </tr>
        </table>
<?php } else { ?>
                        <input type="hidden" name="threadid" value="<?=$ThreadID?>" />
<?php } ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" value="Search" />
                    </td>
                </tr>
            </table>
        </form>

<?php $results = $search->results($paginator); ?>
    <?= $paginator->linkbox() ?>
    <table cellpadding="6" cellspacing="1" border="0" class="forum_list border" width="100%">
    <tr class="colhead">
        <td>Forum</td>
        <td><?=((!empty($ThreadID)) ? 'Post begins' : 'Topic')?></td>
        <td>Topic creation time</td>
        <td>Last post time</td>
    </tr>
<?php if (empty($results)) { ?>
        <tr><td colspan="4">Nothing found<?= !empty($_GET['user']) ? ' (unknown username)' : '' ?>!</td></tr>
<?php }

$Row = 'a'; // For the pretty colours
foreach ($results as $r) {
    [$ID, $Title, $ForumID, $ForumName, $LastTime, $PostID, $Body, $ThreadCreatedTime] = $r;
    $Row = $Row === 'a' ? 'b' : 'a';
    // Print results
?>
        <tr class="row<?=$Row?>">
            <td>
                <a href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>"><?=$ForumName?></a>
            </td>
            <td>
<?php    if (empty($ThreadID)) { ?>
                <a href="forums.php?action=viewthread&amp;threadid=<?=$ID?>"><?= shortenString($Title, 80) ?></a>
<?php    } else { ?>
                <?=shortenString($Title, 80); ?>
<?php
    }
    if ($search->isBodySearch()) { ?>
                <a href="#" onclick="$('#post_<?=$PostID?>_text').gtoggle(); return false;">(Show)</a> <span style="float: right;" class="tooltip last_read" title="Jump to post"><a href="forums.php?action=viewthread&amp;threadid=<?=$ID?><?php if (!empty($PostID)) {
echo "&amp;postid=$PostID#post$PostID"; } ?>"></a></span>
<?php    } ?>
            </td>
            <td>
                <?=time_diff($ThreadCreatedTime)?>
            </td>
            <td>
                <?=time_diff($LastTime)?>
            </td>
        </tr>
<?php    if ($search->isBodySearch()) { ?>
        <tr class="row<?=$Row?> hidden" id="post_<?=$PostID?>_text">
            <td colspan="4"><?=Text::full_format($Body)?></td>
        </tr>
<?php    }
}
?>
    </table>

    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
