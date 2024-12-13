<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

use Gazelle\Enum\CacheBucket;

$forumMan = new Gazelle\Manager\Forum();
if (isset($_GET['postid'])) {
    $post = (new Gazelle\Manager\ForumPost())->findById((int)$_GET['postid']);
    if (is_null($post)) {
        error(404);
    }
    if (!isset($_GET['threadid'])) {
        header("Location: {$post->location()}");
        exit;
    }
    $thread = $post->thread();
} elseif (isset($_GET['threadid'])) {
    $post = null;
    $thread = (new Gazelle\Manager\ForumThread())->findById((int)$_GET['threadid']);
    if (is_null($thread)) {
        error(404);
    }
} else {
    error(404);
}
$threadId = $thread->id();
$forum = $thread->forum();

if (!$Viewer->readAccess($forum)) {
    error(403);
}

//Escape strings for later display
$ForumName = display_str($forum->name());
$IsDonorForum = ($forum->id() == DONOR_FORUM);
$PerPage = $Viewer->postsPerPage();

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
$PostNum = match (true) {
    isset($_GET['post'])        => (int)$_GET['post'],
    $post && !$post->isPinned() => $post->priorPostTotal(),
    default                     => 1,
};

$Page = max(1, (int)($_GET['page'] ?? (int)ceil(min($thread->postTotal(), $PostNum) / $PerPage)));
if (($Page - 1) * $PerPage > $thread->postTotal()) {
    $Page = (int)ceil($thread->postTotal() / $PerPage);
}
$slice = $thread->slice(perPage: $PerPage, page: $Page);
$paginator = new Gazelle\Util\Paginator($PerPage, $Page);
$paginator->setTotal($thread->postTotal());

$firstOnPage = current($slice)['ID'] ?? 0;
$lastOnPage = count($slice) ? end($slice)['ID'] : 0;
if ($lastOnPage <= $thread->pinnedPostId() && $thread->postTotal() <= $PerPage * $Page) {
    $lastOnPage = $thread->pinnedPostId();
}

$quote = new Gazelle\User\Quote($Viewer);
if ($quote->unreadTotal()) {
    $quote->clearThread($threadId, $firstOnPage, $lastOnPage);
}

$lastRead = $thread->userLastReadPost($Viewer);
if ($lastRead < $lastOnPage) {
    $thread->catchup($Viewer, $lastOnPage);
}

$isSubscribed = (new Gazelle\User\Subscription($Viewer))->isSubscribed($threadId);
if ($isSubscribed) {
    $Cache->delete_value('subscriptions_user_new_' . $Viewer->id());
}

$userMan = new Gazelle\Manager\User();
$avatarFilter = Gazelle\Util\Twig::factory()->createTemplate('{{ user|avatar(viewer)|raw }}');

$transitions = (new Gazelle\Manager\ForumTransition())->threadTransitionList($Viewer, $thread);
$department = $forum->departmentList($Viewer);
$auth = $Viewer->auth();

View::show_header("Forums › $ForumName › {$thread->title()}",
     ['js' => 'comments,subscriptions,bbcode' . ($IsDonorForum ? ',donor_titles' : '')]
);
echo $Twig->render('forum/thread-header.twig', [
    'is_subbed'       => $isSubscribed,
    'paginator'       => $paginator,
    'thread'          => $thread,
    'transition_list' => $transitions,
    'viewer'          => $Viewer,
]);

echo $Twig->render('forum/poll.twig', [
    'poll'     => $thread->hasPoll() ? new Gazelle\ForumPoll($threadId) : false,
    'user_man' => $userMan,
    'viewer'   => $Viewer,
]);

// Squeeze in stickypost
if ($thread->pinnedPostId()) {
    if (!$slice) {
        $slice = [$thread->pinnedPostInfo()];
    } else {
        if ($thread->pinnedPostId() != current($slice)['ID']) {
            array_unshift($slice, $thread->pinnedPostInfo());
        }
        if ($thread->pinnedPostId() != $slice[count($slice) - 1]['ID']) {
            $slice[] = $thread->pinnedPostInfo();
        }
    }
}

// Enable TOC
Text::$TOC = true;

foreach ($slice as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime] = array_values($Post);
    $author = new Gazelle\User($AuthorID);
    $tableClass = ['forum_post', 'wrap_overflow', 'box vertical_margin'];
    if (
        (!$thread->isLocked() || $thread->isPinned())
            && $PostID > $lastRead
            && strtotime($AddedTime) > $Viewer->forumCatchupEpoch()
    ) {
        $tableClass[] = 'forum_unread';
    }
    if (!$Viewer->showAvatars()) {
        $tableClass[] = 'noavatar';
    }
    if ($AuthorID == $thread->authorId()) {
        $tableClass[] = 'important_user';
    }
    if ($PostID == $thread->pinnedPostId()) {
        $tableClass[] = 'sticky_post';
    }
?>
<table class="<?= implode(' ', $tableClass) ?>" id="post<?= $PostID ?>">
    <colgroup>
<?php if ($Viewer->showAvatars()) { ?>
        <col class="col_avatar" />
<?php } ?>
        <col class="col_post_body" />
    </colgroup>
    <tr class="colhead_dark">
        <td colspan="<?= $Viewer->showAvatars() ? 2 : 1 ?>">
            <span style="float: left;"><a class="post_id" href="forums.php?action=viewthread&amp;threadid=<?=$threadId?>&amp;postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=Users::format_username($AuthorID, true, true, true, true, true, $IsDonorForum) ?>
                <?=time_diff($AddedTime, 2); ?>
                <span id="postcontrol-<?= $PostID ?>">
<?php if (!$thread->isLocked() && !$Viewer->disablePosting()) { ?>
                - <a href="#quickpost" class="brackets quotable" id="quote_<?=$PostID?>" data-id="<?=$PostID?>" data-author="<?= $author->username() ?>" title="Select text to quote">Quote</a>
<?php
    }
    if ((!$thread->isLocked() && $Viewer->writeAccess($forum) && $AuthorID == $Viewer->id()) && !$Viewer->disablePosting() || $Viewer->permitted('site_moderate_forums')) {
?>
                - <a href="#post<?= $PostID ?>" id="edit-<?= $PostID ?>" data-author="<?= $AuthorID ?>" data-key="<?= $Key ?>" class="edit-post brackets">Edit</a>
<?php } ?>
<?php if ($Viewer->permitted('site_forum_post_delete') && $thread->postTotal() > 1) { ?>
                - <a href="#" data-id="<?= $PostID ?>" class="brackets delete-post">Delete</a>
<?php
    }
    if ($PostID == $thread->pinnedPostId()) { ?>
                <strong><span class="sticky_post_label" class="brackets">Pinned</span></strong>
<?php   if ($Viewer->permitted('site_moderate_forums')) { ?>
                - <a href="forums.php?action=sticky_post&amp;threadid=<?=$threadId?>&amp;postid=<?=$PostID?>&amp;remove=true&amp;auth=<?=$auth?>" title="Unpin this post" class="brackets tooltip">X</a>
<?php
        }
    } else {
        if ($Viewer->permitted('site_moderate_forums')) {
?>
                - <a href="forums.php?action=sticky_post&amp;threadid=<?=$threadId?>&amp;postid=<?=$PostID?>&amp;auth=<?=$auth?>" title="Pin this post" class="tooltip" style="font-size: 1.4em">&#X1f4cc;</a>
<?php
        }
    }
?>
                </span>
            </span>
            <span id="bar<?=$PostID?>" style="float: right">
                <a href="reports.php?action=report&amp;type=post&amp;id=<?=$PostID?>" class="brackets">Report</a>
<?php
    $author = new Gazelle\User($AuthorID);
    if ($Viewer->permitted('users_warn') && $Viewer->id() != $AuthorID && $Viewer->classLevel() >= $author->classLevel()) {
?>
                <form class="manage_form hidden" name="user" id="warn<?=$PostID?>" action="" method="post">
                    <input type="hidden" name="action" value="warn" />
                    <input type="hidden" name="auth" value="<?= $auth ?>" />
                    <input type="hidden" name="postid" value="<?=$PostID?>" />
                    <input type="hidden" name="userid" value="<?=$AuthorID?>" />
                    <input type="hidden" name="key" value="<?=$Key?>" />
                </form>
                - <a href="#" onclick="$('#warn<?=$PostID?>').raw().submit(); return false;" class="brackets">Warn</a>
<?php } ?>
                &nbsp;
                <a href="#">↑</a>
            </span>
        </td>
    </tr>
    <tr>
<?php   if ($Viewer->showAvatars()) { ?>
        <td class="avatar" valign="top">
            <?= $avatarFilter->render(['user' => $author, 'viewer' => $Viewer]) ?>
        </td>
<?php   } ?>
        <td class="body" valign="top"<?php if (!$Viewer->showAvatars()) {
echo ' colspan="2"'; } ?>>
            <div id="content<?=$PostID?>">
                <?= Text::full_format($Body, cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::forum) ?>
<?php   if ($EditedUserID) { ?>
                <br />
                <br />
                <span class="last_edited">
<?php       if ($Viewer->permitted('site_admin_forums')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit('forums', <?=$PostID?>, 1); return false;">&laquo;</a>
<?php       } ?>
                Last edited by
                <?=Users::format_username($EditedUserID, false, false, false, false, false, $IsDonorForum) ?> <?=time_diff($EditedTime, 2)?>
                </span>
<?php    } ?>
            </div>
        </td>
    </tr>
</table>
<?php } ?>
<div class="breadcrumbs">
    <a href="forums.php">Forums</a> › <?= $forum->link() ?> › <?= display_str($thread->title()) ?>
</div>
<?php
echo $paginator->linkbox();
$lastPost = end($slice);

if ($Viewer->permitted('site_moderate_forums') || ($Viewer->writeAccess($forum) && !$thread->isLocked())) {
    echo $Twig->render('reply.twig', [
        'object'   => $thread,
        'merge'    => strtotime($lastPost['AddedTime']) > time() - 3600 && $lastPost['AuthorID'] == $Viewer->id(),
        'subbed'   => $isSubscribed,
        'textarea' => (new Gazelle\Util\Textarea('quickpost', '', 90, 8))->setPreviewManual(true),
        'viewer'   => $Viewer,
    ]);
}

echo $Twig->render('forum/thread-footer.twig', [
    'forum_list'      => $forumMan->forumList(),
    'page'            => $Page,
    'thread'          => $thread,
    'transition_list' => $transitions,
    'viewer'          => $Viewer,
]);
