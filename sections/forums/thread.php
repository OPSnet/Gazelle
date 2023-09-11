<?php

use Gazelle\Enum\CacheBucket;

/**********|| Page to show individual threads || ********************************\

Things to expect in $_GET:
    ThreadID: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

$forumMan = new Gazelle\Manager\Forum;
if (isset($_GET['postid'])) {
    $post = (new Gazelle\Manager\ForumPost)->findById((int)$_GET['postid']);
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
    $thread = (new Gazelle\Manager\ForumThread)->findById((int)$_GET['threadid']);
    if (is_null($thread)) {
        error(404);
    }
} else {
    error(404);
}
$threadId = $thread->id();
$forum = $thread->forum();
$forumId = $forum->id();

if (!$Viewer->readAccess($forum)) {
    error(403);
}

//Escape strings for later display
$ForumName = display_str($forum->name());
$IsDonorForum = ($forumId == DONOR_FORUM);
$PerPage = $Viewer->postsPerPage();

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
$PostNum = match(true) {
    isset($_GET['post'])        => (int)$_GET['post'],
    $post && !$post->isSticky() => $post->priorPostTotal(),
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

$lastRead = $thread->userLastReadPost($Viewer->id());
if ($lastRead < $lastOnPage) {
    $thread->catchup($Viewer->id(), $lastOnPage);
}

$isSubscribed = (new Gazelle\User\Subscription($Viewer))->isSubscribed($threadId);
if ($isSubscribed) {
    $Cache->delete_value('subscriptions_user_new_'.$Viewer->id());
}

$userMan = new Gazelle\Manager\User;
$avatarFilter = Gazelle\Util\Twig::factory()->createTemplate('{{ user|avatar(viewer)|raw }}');

$transitions = $forumMan->threadTransitionList($Viewer, $forumId);
$department = $forum->departmentList($Viewer);
$auth = $Viewer->auth();
View::show_header("Forums › $ForumName › {$thread->title()}",
     ['js' => 'comments,subscriptions,bbcode' . ($IsDonorForum ? ',donor_titles' : '')]
);
echo $Twig->render('forum/header-thread.twig', [
    'auth'         => $auth,
    'forum'        => $forum,
    'dept_list'    => $forum->departmentList($Viewer),
    'is_subbed'    => $isSubscribed,
    'paginator'    => $paginator,
    'thread_id'    => $threadId,
    'thread_title' => $thread->title(),
    'transition'   => $transitions,
]);

if ($thread->hasPoll()) {
    $poll = new Gazelle\ForumPoll($threadId);

    $RevealVoters = $forum->hasRevealVotes();
    $response = $poll->response($Viewer);
    $answerList = $poll->vote();
    if ($response > 0 || (!is_null($response) && $RevealVoters)) {
        $answerList[$response]['answer'] = '&raquo; ' . $answerList[$response]['answer'];
    }
?>
    <div class="box thin clear">
        <div class="head colhead_dark"><strong>Poll<?php if ($poll->isClosed()) { echo ' [Closed]'; } ?><?php if ($poll->isFeatured()) { echo ' [Featured]'; } ?></strong> <a href="#" onclick="$('#threadpoll').gtoggle(); log_hit(); return false;" class="brackets">View</a></div>
        <div class="pad<?php if ($thread->isLocked()) { echo ' hidden'; } ?>" id="threadpoll">
            <p><strong><?=display_str($poll->question())?></strong></p>
<?php if ($response !== null || $poll->isClosed() || $thread->isLocked()) { ?>
            <ul class="poll nobullet">
<?php
        if (!$RevealVoters) {
            foreach ($answerList as $choice) {
?>
                    <li><?=display_str($choice['answer'])?> (<?=number_format($choice['percent'], 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($choice['ratio'], 2)?>%;"></span>
                        <span class="right_poll"></span>
                    </li>
<?php
            }
            if (isset($answerList[0]) and $answerList[0]['total'] > 0) {
?>
                <li><?=($response == '0' ? '&raquo; ' : '')?>(Blank) (<?=number_format($answerList[0]['total'], 2)?>%)</li>
                <li class="graph">
                    <span class="left_poll"></span>
                    <span class="center_poll" style="width: <?=number_format($answerList[0]['ratio'], 2)?>%;"></span>
                    <span class="right_poll"></span>
                </li>
<?php       } ?>
            </ul>
            <br />
            <strong>Votes:</strong> <?= number_format($poll->total()) ?><br /><br />
<?php       } else { ?>
            <ul class="nobullet" id="poll_options">
<?php
            // Staff forum, output voters, not percentages
            $totalStaff = 0;
            $totalVoted = 0;
            $vote = $poll->staffVote($userMan);
            foreach ($vote as $response => $info) {
                if ($response !== 'missing') {
                    $totalStaff += count($info['who']);
                    $totalVoted += count($info['who']);
?>
                <li><a href="forums.php?action=change_vote&amp;threadid=<?=$threadId?>&amp;auth=<?= $auth ?>&amp;vote=<?= $response ?>"><?=empty($info['answer']) ? 'Abstain' : display_str($info['answer']) ?></a> <?=
                    count ($info['who']) ? (" \xE2\x80\x93 " . implode(', ', array_map(fn($u) => $u->link(), $info['who']))) : "<i>none</i>"
                ?></li>
<?php
                }
            }
            if (count($vote['missing']['who'])) {
                $totalStaff += count($vote['missing']['who']);
?>
                <li>Missing: <?= implode(', ', array_map(fn($u) => $u->link(), $vote['missing']['who'])) ?></li>
<?php       } ?>
            </ul>
<?php       if (in_array($forumId, FORUM_REVEAL_VOTE)) { ?>
            <br />
            <strong>Voted:</strong> <?=number_format($totalVoted)?> of <?=number_format($totalStaff)?> total. (You may click on a choice to change your vote).
            <br />
<?php       } ?>
            <a href="#" onclick="AddPollOption(<?=$threadId?>); return false;" class="brackets">+</a>
<?php
        }
    } else {
    //User has not voted
?>
            <div id="poll_container">
                <form class="vote_form" name="poll" id="poll" action="">
                    <input type="hidden" name="action" value="poll" />
                    <input type="hidden" name="auth" value="<?=$auth?>" />
                    <input type="hidden" name="large" value="1" />
                    <input type="hidden" name="threadid" value="<?=$threadId?>" />
                    <ul class="nobullet" id="poll_options">
<?php    foreach ($answerList as $response => $choice) { ?>
                        <li>
                            <input type="radio" name="vote" id="answer_<?=$response?>" value="<?=$response?>" />
                            <label for="answer_<?=$response?>"><?=display_str($choice['answer'])?></label>
                        </li>
<?php    } ?>
                        <li>
                            <br />
                            <input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Abstain</label><br />
                        </li>
                    </ul>
<?php    if ($Viewer->isStaff() && in_array($forumId, FORUM_REVEAL_VOTE)) { ?>
                    <a href="#" onclick="AddPollOption(<?=$threadId?>); return false;" class="brackets">+</a>
                    <br />
                    <br />
<?php    } ?>
                    <input type="button" style="float: left;" onclick="ajax.post('index.php','poll',function(response) { $('#poll_container').raw().innerHTML = response});" value="Vote" />
                </form>
            </div>
<?php
    }
    if ($Viewer->permitted('forums_polls_moderate') && !$RevealVoters) {
        if (!$poll->isFeatured()) {
?>
            <form class="manage_form" name="poll" action="forums.php" method="post">
                <input type="hidden" name="action" value="poll_mod" />
                <input type="hidden" name="auth" value="<?=$auth?>" />
                <input type="hidden" name="threadid" value="<?=$threadId?>" />
                <input type="hidden" name="feature" value="1" />
                <input type="submit" style="float: left;" onclick="return confirm('Are you sure you want to feature this poll?');" value="Feature" />
            </form>
<?php   } ?>
            <form class="manage_form" name="poll" action="forums.php" method="post">
                <input type="hidden" name="action" value="poll_mod" />
                <input type="hidden" name="auth" value="<?=$auth?>" />
                <input type="hidden" name="threadid" value="<?=$threadId?>" />
                <input type="hidden" name="close" value="1" />
                <input type="submit" style="float: left;" value="<?= $poll->isClosed() ? 'Open' : 'Close' ?>" />
            </form>
<?php } ?>
        </div>
    </div>
<?php
} //End Polls

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
                - <a href="#quickpost" id="quote_<?=$PostID?>" onclick="Quote('<?=$PostID?>', '<?= $author->username() ?>', true);" title="Select text to quote" class="brackets">Quote</a>
<?php
    }
    if ((!$thread->isLocked() && $Viewer->writeAccess($forum) && $AuthorID == $Viewer->id()) && !$Viewer->disablePosting() || $Viewer->permitted('site_moderate_forums')) {
?>
                - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>', '<?=$Key?>');" class="brackets">Edit</a>
<?php } ?>
<?php if ($Viewer->permitted('site_forum_post_delete') && $thread->postTotal() > 1) { ?>
                - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');" class="brackets">Delete</a>
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
                <a href="#">&uarr;</a>
            </span>
        </td>
    </tr>
    <tr>
<?php   if ($Viewer->showAvatars()) { ?>
        <td class="avatar" valign="top">
            <?= $avatarFilter->render(['user' => $author, 'viewer' => $Viewer]) ?>
        </td>
<?php   } ?>
        <td class="body" valign="top"<?php if (!$Viewer->showAvatars()) { echo ' colspan="2"'; } ?>>
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
    <a href="forums.php">Forums</a> &rsaquo; <?= $forum->link() ?> &rsaquo; <?= display_str($thread->title()) ?>
</div>
<?php
echo $paginator->linkbox();
$lastPost = end($slice);

if ($Viewer->permitted('site_moderate_forums') || ($Viewer->writeAccess($forum) && !$thread->isLocked())) {
    echo $Twig->render('reply.twig', [
        'action'   => 'reply',
        'forum'    => $forumId,
        'id'       => $threadId,
        'merge'    => strtotime($lastPost['AddedTime']) > time() - 3600 && $lastPost['AuthorID'] == $Viewer->id(),
        'name'     => 'threadid',
        'subbed'   => $isSubscribed,
        'textarea' => (new Gazelle\Util\Textarea('quickpost', '', 90, 8))->setPreviewManual(true),
        'userMan'  => $userMan,
        'viewer'   => $Viewer,
    ]);
}

if (count($transitions)) {
?>
    <table class="layout border">
        <tr>
            <td class="label">Move thread</td>
            <td>
<?php foreach ($transitions as $transition) { ?>
                <form action="forums.php" method="post" style="display: inline-block">
                    <input type="hidden" name="action" value="mod_thread" />
                    <input type="hidden" name="auth" value="<?=$auth?>" />
                    <input type="hidden" name="threadid" value="<?=$threadId?>" />
                    <input type="hidden" name="page" value="<?=$Page?>" />
                    <input type="hidden" name="transition" value="<?=$transition['id']?>" />
                    <input type="submit" value="<?=$transition['label']?>" />
                </form>
<?php } ?>
            </td>
        </tr>
    </table>
<?php
}
if ($Viewer->permitted('site_moderate_forums')) {
    $Notes = $thread->threadNotes();
?>
    <br />
    <h3 id="thread_notes">Thread notes</h3> <a href="#" onclick="$('#thread_notes_table').gtoggle(); return false;" class="brackets">Toggle</a>
    <form action="forums.php" method="post">
        <input type="hidden" name="action" value="take_topic_notes" />
        <input type="hidden" name="auth" value="<?=$auth?>" />
        <input type="hidden" name="threadid" value="<?=$threadId?>" />
        <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border hidden" id="thread_notes_table">
<?php foreach ($Notes as $Note) { ?>
            <tr><td><?=Users::format_username($Note['AuthorID'])?> (<?=time_diff($Note['AddedTime'], 2, true)?>)</td><td><?=Text::full_format($Note['Body'])?></td></tr>
<?php } ?>
            <tr>
                <td colspan="2" class="center">
                    <div class="field_div textarea_wrap"><textarea id="topic_notes" name="body" cols="90" rows="3" onkeyup="resize('threadnotes');" style=" margin: 0px; width: 735px;"></textarea></div>
                    <input type="submit" value="Save" />
                </td>
            </tr>
        </table>
    </form>
    <br />
    <h3>Edit thread</h3>
    <form class="edit_form" name="forum_thread" action="forums.php" method="post">
        <div>
        <input type="hidden" name="action" value="mod_thread" />
        <input type="hidden" name="auth" value="<?=$auth?>" />
        <input type="hidden" name="threadid" value="<?=$threadId?>" />
        <input type="hidden" name="page" value="<?=$Page?>" />
        </div>
        <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border">
            <tr>
                <td class="label"><label for="sticky_thread_checkbox" title="Pin this thread at the top of the list of threads">Pin</label></td>
                <td>
                    <input type="checkbox" id="sticky_thread_checkbox" onclick="$('#ranking_row').gtoggle();" name="sticky"<?php if ($thread->isPinned()) { echo ' checked="checked"'; } ?> tabindex="4" />
                </td>
            </tr>
            <tr id="ranking_row"<?= $thread->isPinned() ? '' : ' class="hidden"' ?>>
                <td class="label"><label for="thread_ranking_textbox">Ranking</label></td>
                <td>
                    <input type="text" id="thread_ranking_textbox" name="ranking" value="<?=$thread->pinnedRanking()?>" tabindex="5" />
                </td>
            </tr>
            <tr>
                <td class="label"><label for="locked_thread_checkbox">Locked</label></td>
                <td>
                    <input type="checkbox" id="locked_thread_checkbox" name="locked"<?php if ($thread->isLocked()) { echo ' checked="checked"'; } ?> tabindex="6" />
                </td>
            </tr>
            <tr>
                <td class="label"><label for="thread_title_textbox">Title</label></td>
                <td>
                    <input type="text" id="thread_title_textbox" name="title" style="width: 75%;" value="<?=display_str($thread->title())?>" tabindex="7" />
                </td>
            </tr>
            <tr>
                <td class="label"><label for="move_thread_selector">Move thread</label></td>
                <td>
                    <select name="forumid" id="move_thread_selector" tabindex="8">
<?php
    $OpenGroup = false;
    $LastCategoryID = -1;
    $Forums = (new Gazelle\Manager\Forum)->forumList();
    foreach ($Forums as $forumId) {
        $forum = new Gazelle\Forum($forumId);
        if (!$Viewer->readAccess($forum)) {
            continue;
        }

        if ($forum->categoryId() != $LastCategoryID) {
            $LastCategoryID = $forum->categoryId();
            if ($OpenGroup) {
                $OpenGroup = true;
?>
                    </optgroup>
<?php       } ?>
                    <optgroup label="<?= $forum->categoryName() ?>">
<?php   } ?>
                        <option value="<?= $forumId ?>"<?php if ($thread->forumId() == $forumId) { echo ' selected="selected"';} ?>><?=display_str($forum->name())?></option>
<?php } // foreach ?>
                    </optgroup>
                    </select>
                </td>
            </tr>
<?php    if ($Viewer->permitted('site_admin_forums')) { ?>
            <tr>
                <td class="label"><label for="delete_thread_checkbox">Delete thread</label></td>
                <td>
                    <input type="checkbox" id="delete_thread_checkbox" name="delete" tabindex="2" />
                </td>
            </tr>
<?php    } ?>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Edit thread" tabindex="3" />
                </td>
            </tr>
        </table>
    </form>
<?php } // $Viewer->permitted('site_moderate_forums') ?>
</div>
<?php
View::show_footer();
