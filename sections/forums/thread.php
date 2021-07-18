<?php
//TODO: Normalize thread_*_info don't need to waste all that ram on things that are already in other caches
/**********|| Page to show individual threads || ********************************\

Things to expect in $_GET:
    ThreadID: ID of the forum curently being browsed
    page:    The page the user's on.
    page = 1 is the same as no page

********************************************************************************/

//---------- Things to sort out before it can start printing/generating content

// Enable TOC
Text::$TOC = true;

$forumMan = new Gazelle\Manager\Forum;
if (isset($_GET['postid'])) {
    $postId = (int)$_GET['postid'];
    $forum = $forumMan->findByPostId($postId);
    if (is_null($forum)) {
        error(404);
    }
    if (!isset($_GET['threadid'])) {
        header("Location: forums.php?action=viewthread&threadid="
            . $forum->findThreadIdByPostId($postId) . "&postid=$postId#post$postId"
        );
        exit;
    }
    $threadId = $forum->findThreadIdByPostId($postId);
} elseif (isset($_GET['threadid'])) {
    $postId = false;
    $threadId = (int)$_GET['threadid'];
    $forum = $forumMan->findByThreadId($threadId);
    if (is_null($forum)) {
        error(404);
    }
} else {
    error(404);
}
$forumId = $forum->id();
$threadInfo = $forum->threadInfo($threadId);
if (empty($threadInfo)) {
    error(404);
}
if (!$Viewer->readAccess($forum)) {
    error(403);
}

//Escape strings for later display
$ForumName = display_str($forum->name());
$IsDonorForum = ($forumId == DONOR_FORUM);
$PerPage = $Viewer->postsPerPage();

//Post links utilize the catalogue & key params to prevent issues with custom posts per page
if ($threadInfo['Posts'] <= $PerPage) {
    $PostNum = 1;
} else {
    if (isset($_GET['post'])) {
        $PostNum = (int)($_GET['post'] ?? 1);
    } elseif ($postId && $postId != $threadInfo['StickyPostID']) {
        $PostNum = $forum->threadNumPosts($threadId, $postId, $threadInfo['StickyPostID'] < $postId);
    } else {
        $PostNum = 1;
    }
}

$Page = max(1, isset($_GET['page'])
    ? (int)$_GET['page']
    : (int)ceil(min($threadInfo['Posts'], $PostNum) / $PerPage)
);
if (($Page - 1) * $PerPage > $threadInfo['Posts']) {
    $Page = (int)ceil($threadInfo['Posts'] / $PerPage);
}
$thread = $forum->threadPage($threadId, $PerPage, $Page);
$paginator = new Gazelle\Util\Paginator($PerPage, $Page);
$paginator->setTotal($threadInfo['Posts']);

$firstOnPage = current($thread)['ID'];
$lastOnPage = end($thread)['ID'];
if ($lastOnPage <= $threadInfo['StickyPostID'] && $threadInfo['Posts'] <= $PerPage * $Page) {
    $lastOnPage = $threadInfo['StickyPostID'];
}

$quoteCount = $Cache->get_value('notify_quoted_' . $Viewer->id());
if ($quoteCount === false || $quoteCount > 0) {
    (new Gazelle\User\Quote($Viewer))->clearThread($threadId, $firstOnPage, $lastOnPage);
}

$lastRead = $forum->userLastReadPost($Viewer->id(), $threadId);
if ($lastRead < $lastOnPage) {
    $forum->userCatchupThread($Viewer->id(), $threadId, $lastOnPage);
}

$isSubscribed = (new Gazelle\Manager\Subscription($Viewer->id()))->isSubscribed($threadId);
if ($isSubscribed) {
    $Cache->delete_value('subscriptions_user_new_'.$Viewer->id());
}

$userMan = new Gazelle\Manager\User;

$transitions = $forumMan->threadTransitionList($Viewer, $forumId);
$department = $forum->departmentList($Viewer);
$auth = $Viewer->auth();
View::show_header("Forums &rsaquo; $ForumName &rsaquo; " . display_str($threadInfo['Title']),
     ['js' => 'comments,subscriptions,bbcode' . ($IsDonorForum ? ',donor' : '')]
);
echo $Twig->render('forum/header-thread.twig', [
    'auth'         => $auth,
    'forum'        => $forum,
    'dept_list'    => $forum->departmentList($Viewer),
    'is_subbed'    => $isSubscribed,
    'paginator'    => $paginator,
    'thread_id'    => $threadId,
    'thread_title' => $threadInfo['Title'],
    'transition'   => $transitions,
]);

if ($threadInfo['NoPoll'] == 0) {
    [$Question, $Answers, $Votes, $Featured, $Closed] = $forum->pollData($threadId);

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $RevealVoters = $forum->hasRevealVotes();
    $UserResponse = $forum->pollVote($Viewer->id(), $threadId);
    if ($UserResponse > 0) {
        $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
    } else {
        if (!is_null($UserResponse) && $RevealVoters) {
            $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
        }
    }
?>
    <div class="box thin clear">
        <div class="head colhead_dark"><strong>Poll<?php if ($Closed) { echo ' [Closed]'; } ?><?php if ($Featured) { echo ' [Featured]'; } ?></strong> <a href="#" onclick="$('#threadpoll').gtoggle(); log_hit(); return false;" class="brackets">View</a></div>
        <div class="pad<?php if ($threadInfo['isLocked']) { echo ' hidden'; } ?>" id="threadpoll">
            <p><strong><?=display_str($Question)?></strong></p>
<?php if ($UserResponse !== null || $Closed || $threadInfo['isLocked']) { ?>
            <ul class="poll nobullet">
<?php
        if (!$RevealVoters) {
            foreach ($Answers as $i => $Answer) {
                if (!empty($Votes[$i]) && $TotalVotes > 0) {
                    $Ratio = $Votes[$i] / $MaxVotes;
                    $Percent = $Votes[$i] / $TotalVotes;
                } else {
                    $Ratio = 0;
                    $Percent = 0;
                }
?>
                    <li><?=display_str($Answer)?> (<?=number_format($Percent * 100, 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($Ratio * 100, 2)?>%;"></span>
                        <span class="right_poll"></span>
                    </li>
<?php
            }
            if ($Votes[0] ?? 0 > 0) {
?>
                <li><?=($UserResponse == '0' ? '&raquo; ' : '')?>(Blank) (<?=number_format((float)($Votes[0] ?? 0 / $TotalVotes * 100), 2)?>%)</li>
                <li class="graph">
                    <span class="left_poll"></span>
                    <span class="center_poll" style="width: <?=number_format((float)($Votes[0] / $MaxVotes * 100), 2)?>%;"></span>
                    <span class="right_poll"></span>
                </li>
<?php       } ?>
            </ul>
            <br />
            <strong>Votes:</strong> <?=number_format($TotalVotes)?><br /><br />
<?php
        } else {
            //Staff forum, output voters, not percentages
            $names = array_map(function ($s) { return $s->username(); }, $userMan->staffList());
            $staffCount = count($names);
            $votes = $forum->staffVote($threadId);
            $StaffVotes = [];
            foreach ($votes as list($who, $Vote)) {
                $StaffVotes[$Vote] = $who;
                $names = array_diff($names, explode(', ', $who));
            }
?>
            <ul class="nobullet" id="poll_options">
<?php       foreach ($Answers as $i => $Answer) { ?>
                <li>
                    <a href="forums.php?action=change_vote&amp;threadid=<?=$threadId?>&amp;auth=<?=$auth?>&amp;vote=<?=(int)$i?>"><?=$Answer == '' ? 'Blank' : display_str($Answer)?></a>
                     - <?=$StaffVotes[$i]?>&nbsp;(<?=number_format(((float)$Votes[$i] / $TotalVotes) * 100, 2)?>%)
                    <a href="forums.php?action=delete_poll_option&amp;threadid=<?=$threadId?>&amp;auth=<?=$auth?>&amp;vote=<?=(int)$i?>" onclick="return confirm('Are you sure you want to delete this poll option?');" class="brackets tooltip" title="Delete poll option">X</a>
                </li>
<?php       } ?>
                <li>
                    <a href="forums.php?action=change_vote&amp;threadid=<?=$threadId?>&amp;auth=<?=$auth?>&amp;vote=0"><?=($UserResponse == '0' ? '&raquo; ' : '')?>Blank</a> - <?=$StaffVotes[0]?>&nbsp;(<?=number_format(((float)$Votes[0] / $TotalVotes) * 100, 2)?>%)
                </li>
            </ul>
<?php       if ($forumId == STAFF_FORUM_ID) { ?>
            <br />
            <strong>Votes:</strong> <?=number_format($staffCount - count($names))?> / <?=$staffCount?> current staff, <?=number_format($TotalVotes)?> total
            <br />
            <strong>Missing votes:</strong> <?=implode(", ", $names); echo "\n";?>
            <br /><br />
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
<?php    foreach ($Answers as $i => $Answer) { ?>
                        <li>
                            <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
                            <label for="answer_<?=$i?>"><?=display_str($Answer)?></label>
                        </li>
<?php    } ?>
                        <li>
                            <br />
                            <input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br />
                        </li>
                    </ul>
<?php    if ($forumId == STAFF_FORUM_ID) { ?>
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
        if (!$Featured) {
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
                <input type="submit" style="float: left;" value="<?=(!$Closed ? 'Close' : 'Open')?>" />
            </form>
<?php } ?>
        </div>
    </div>
<?php
} //End Polls

// Squeeze in stickypost
if ($threadInfo['StickyPostID']) {
    if ($threadInfo['StickyPostID'] != current($thread)['ID']) {
        array_unshift($thread, $threadInfo['StickyPost']);
    }
    if ($threadInfo['StickyPostID'] != $thread[count($thread) - 1]['ID']) {
        $thread[] = $threadInfo['StickyPost'];
    }
}

foreach ($thread as $Key => $Post) {
    [$PostID, $AuthorID, $AddedTime, $Body, $EditedUserID, $EditedTime] = array_values($Post);
    $author = new Gazelle\User($AuthorID);
    $tableClass = ['forum_post', 'wrap_overflow', 'box vertical_margin'];
    if (((!$threadInfo['isLocked'] || $threadInfo['isSticky'])
            && $PostID > $lastRead
            && strtotime($AddedTime) > $Viewer->forumCatchupEpoch()
            ) || (isset($RequestKey) && $Key == $RequestKey)
        ) {
        $tableClass[] = 'forum_unread';
    }
    if (!$Viewer->showAvatars()) {
        $tableClass[] = 'noavatar';
    }
    if ($threadInfo['AuthorID'] == $AuthorID) {
        $tableClass[] = 'important_user';
    }
    if ($PostID == $threadInfo['StickyPostID']) {
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
<?php if (!$threadInfo['isLocked'] && !$Viewer->disablePosting()) { ?>
                - <a href="#quickpost" id="quote_<?=$PostID?>" onclick="Quote('<?=$PostID?>', '<?= $author->username() ?>', true);" title="Select text to quote" class="brackets">Quote</a>
<?php
    }
    if ((!$threadInfo['isLocked'] && $Viewer->writeAccess($forum) && $AuthorID == $Viewer->id()) && !$Viewer->disablePosting() || $Viewer->permitted('site_moderate_forums')) {
?>
                - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>', '<?=$Key?>');" class="brackets">Edit</a>
<?php } ?>
<?php if ($Viewer->permitted('site_forum_post_delete') && $threadInfo['Posts'] > 1) { ?>
                - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');" class="brackets">Delete</a>
<?php
    }
    if ($PostID == $threadInfo['StickyPostID']) { ?>
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
            <?= $userMan->avatarMarkup($Viewer, $author) ?>
        </td>
<?php   } ?>
        <td class="body" valign="top"<?php if (!$Viewer->showAvatars()) { echo ' colspan="2"'; } ?>>
            <div id="content<?=$PostID?>">
                <?= Text::full_format($Body) ?>
<?php   if ($EditedUserID) { ?>
                <br />
                <br />
                <span class="last_edited">
<?php       if ($Viewer->permitted('site_admin_forums')) { ?>
                <a href="#content<?=$PostID?>" onclick="LoadEdit('forums', <?=$PostID?>, 1); return false;">&laquo;</a>
<?php       } ?>
                Last edited by
                <?=Users::format_username($EditedUserID, false, false, false, false, false, $IsDonorForum) ?> <?=time_diff($EditedTime, 2, true, true)?>
                </span>
<?php    } ?>
            </div>
        </td>
    </tr>
</table>
<?php } ?>
<div class="breadcrumbs">
    <a href="forums.php">Forums</a> &rsaquo;
    <a href="forums.php?action=viewforum&amp;forumid=<?=$threadInfo['ForumID']?>"><?=$ForumName?></a> &rsaquo;
    <?= display_str($threadInfo['Title']) ?>
</div>
<?php
echo $paginator->linkbox();
$lastPost = end($thread);
$textarea = new Gazelle\Util\Textarea('quickpost', '', 90, 8);
$textarea->setAutoResize()->setPreviewManual(true);

if ($Viewer->permitted('site_moderate_forums') || ($Viewer->writeAccess($forum) && !$threadInfo['isLocked'])) {
    echo $Twig->render('reply.twig', [
        'auth'     => $auth,
        'action'   => 'reply',
        'forum'    => $forumId,
        'id'       => $threadId,
        'merge'    => strtotime($lastPost['AddedTime']) > time() - 3600 && $lastPost['AuthorID'] == $Viewer->id(),
        'name'     => 'thread',
        'subbed'   => $isSubscribed,
        'textarea' => $textarea,
        'user'     => $Viewer,
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
    $Notes = $forum->threadNotes($threadId);
?>
    <br />
    <h3 id="thread_notes">Thread notes</h3> <a href="#" onclick="$('#thread_notes_table').gtoggle(); return false;" class="brackets">Toggle</a>
    <form action="forums.php" method="post">
        <input type="hidden" name="action" value="take_topic_notes" />
        <input type="hidden" name="auth" value="<?=$auth?>" />
        <input type="hidden" name="threadid" value="<?=$threadId?>" />
        <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border hidden" id="thread_notes_table">
<?php foreach ($Notes as $Note) { ?>
            <tr><td><?=Users::format_username($Note['AuthorID'])?> (<?=time_diff($Note['AddedTime'], 2, true, true)?>)</td><td><?=Text::full_format($Note['Body'])?></td></tr>
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
                    <input type="checkbox" id="sticky_thread_checkbox" onclick="$('#ranking_row').gtoggle();" name="sticky"<?php if ($threadInfo['isSticky']) { echo ' checked="checked"'; } ?> tabindex="4" />
                </td>
            </tr>
            <tr id="ranking_row"<?=!$threadInfo['isSticky'] ? ' class="hidden"' : ''?>>
                <td class="label"><label for="thread_ranking_textbox">Ranking</label></td>
                <td>
                    <input type="text" id="thread_ranking_textbox" name="ranking" value="<?=$threadInfo['Ranking']?>" tabindex="5" />
                </td>
            </tr>
            <tr>
                <td class="label"><label for="locked_thread_checkbox">Locked</label></td>
                <td>
                    <input type="checkbox" id="locked_thread_checkbox" name="locked"<?php if ($threadInfo['isLocked']) { echo ' checked="checked"'; } ?> tabindex="6" />
                </td>
            </tr>
            <tr>
                <td class="label"><label for="thread_title_textbox">Title</label></td>
                <td>
                    <input type="text" id="thread_title_textbox" name="title" style="width: 75%;" value="<?=display_str($threadInfo['Title'])?>" tabindex="7" />
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
                        <option value="<?= $forumId ?>"<?php if ($threadInfo['ForumID'] == $forumId) { echo ' selected="selected"';} ?>><?=display_str($forum->name())?></option>
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
view::show_footer();
