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
$user = new Gazelle\User($LoggedUser['ID']);
if (!$user->readAccess($forum)) {
    error(403);
}

//Escape strings for later display
$threadTitle = display_str($threadInfo['Title']);
$ForumName = display_str($forum->name());
$IsDonorForum = ($forumId == DONOR_FORUM);
$PerPage = $LoggedUser['PostsPerPage'] ?? POSTS_PER_PAGE;

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
[$Page] = Format::page_limit($PerPage, min($threadInfo['Posts'], $PostNum));
if (($Page - 1) * $PerPage > $threadInfo['Posts']) {
    $Page = (int)ceil($threadInfo['Posts'] / $PerPage);
}

$Catalogue = $forum->threadCatalog($threadId, $PerPage, $Page, THREAD_CATALOGUE);
$thread = array_slice($Catalogue, (($Page - 1) * $PerPage) % THREAD_CATALOGUE, $PerPage, true);
$FirstPost = current($thread)['ID'];
$LastPost = end($thread)['ID'];
if ($threadInfo['Posts'] <= $PerPage * $Page && $threadInfo['StickyPostID'] > $LastPost) {
    $LastPost = $threadInfo['StickyPostID'];
}

//Handle last read
if (!$threadInfo['isLocked'] || $threadInfo['isSticky']) {
    $lastRead = $forum->userLastReadPost($user->id(), $threadId);
    if ($lastRead < $LastPost) {
        $forum->userCatchupThread($user->id(), $threadId, $LastPost);
    }
}

$isSubscribed = (new Gazelle\Manager\Subscription($user->id()))->isSubscribed($threadId);
if ($isSubscribed) {
    $Cache->delete_value('subscriptions_user_new_'.$user->id());
}

$QuoteNotificationsCount = $Cache->get_value('notify_quoted_' . $user->id());
if ($QuoteNotificationsCount === false || $QuoteNotificationsCount > 0) {
    (new Gazelle\User\Quote($user))->clearThread($threadId, $FirstPost, $LastPost);
}

$userMan = new Gazelle\Manager\User;

$Pages = Format::get_pages($Page, $threadInfo['Posts'], $PerPage, 9);
$transitions = Forums::get_thread_transitions($forumId);
$auth = $LoggedUser['AuthKey'];
View::show_header("Forums &rsaquo; $ForumName &rsaquo; " . display_str($threadInfo['Title']),
    'comments,subscriptions,bbcode', $IsDonorForum ? ',donor' : '');
?>
<div class="thin">
    <h2>
        <a href="forums.php">Forums</a> &rsaquo;
        <a href="forums.php?action=viewforum&amp;forumid=<?=$threadInfo['ForumID']?>"><?=$ForumName?></a> &rsaquo;
        <?=$threadTitle?>
    </h2>
    <div class="linkbox">
        <div class="center">
            <a href="reports.php?action=report&amp;type=thread&amp;id=<?=$threadId?>" class="brackets">Report thread</a>
            <a href="#" onclick="Subscribe(<?=$threadId?>);return false;" id="subscribelink<?=$threadId?>" class="brackets"><?= $isSubscribed ? 'Unsubscribe' : 'Subscribe' ?></a>
            <a href="#" onclick="$('#searchthread').gtoggle(); this.innerHTML = (this.innerHTML == 'Search this thread' ? 'Hide search' : 'Search this thread'); return false;" class="brackets">Search this thread</a>
        </div>
        <div id="searchthread" class="hidden center">
            <div style="display: inline-block;">
                <h3>Search this thread:</h3>
                <form class="search_form" name="forum_thread" action="forums.php" method="get">
                    <input type="hidden" name="action" value="search" />
                    <input type="hidden" name="threadid" value="<?=$threadId?>" />
                    <table cellpadding="6" cellspacing="1" border="0" class="layout border">
                        <tr>
                            <td><strong>Search for:</strong></td>
                            <td><input type="search" id="searchbox" name="search" size="70" /></td>
                        </tr>
                        <tr>
                            <td><strong>Posted by:</strong></td>
                            <td><input type="search" id="username" name="user" placeholder="Username" size="70" /></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: center;">
                                <input type="submit" name="submit" value="Search" />
                            </td>
                        </tr>
                    </table>
                </form>
                <br />
            </div>
        </div>
<?= $Pages; ?>
    </div>
<?php if ($transitions) { ?>
    <table class="layout border">
        <tr>
            <td class="label">Move thread</td>
            <td>
<?php   foreach ($transitions as $transition) { ?>
                <form action="forums.php" method="post" style="display: inline-block">
                    <input type="hidden" name="action" value="mod_thread" />
                    <input type="hidden" name="auth" value="<?=$auth?>" />
                    <input type="hidden" name="threadid" value="<?=$threadId?>" />
                    <input type="hidden" name="page" value="<?=$Page?>" />
                    <input type="hidden" name="title" value="<?=display_str($threadInfo['Title'])?>" />
                    <input type="hidden" name="transition" value="<?=$transition['id']?>" />
                    <input type="submit" value="<?=$transition['label']?>" />
                </form>
<?php   } ?>
            </td>
        </tr>
    </table>
<?php
}
if ($threadInfo['NoPoll'] == 0) {
    [$Question, $Answers, $Votes, $Featured, $Closed] = $forum->pollData($threadId);

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $RevealVoters = in_array($forumId, $ForumsRevealVoters);
    $UserResponse = $forum->pollVote($user->id(), $threadId);
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
            require_once(__DIR__ . '/../staff/functions.php');
            $Staff = get_staff();
            $StaffNames = [];
            foreach ($Staff as $Group) {
                foreach ($Group as $Staffer) {
                    $StaffNames[] = $Staffer['Username'];
                }
            }

            $StaffVotesTmp = $forum->staffVote($threadId);
            $StaffCount = count($StaffNames);
            $StaffVotes = [];
            foreach ($StaffVotesTmp as $StaffVote) {
                [$Vote, $Names] = $StaffVote;
                $StaffVotes[$Vote] = $Names;
                $Names = explode(', ', $Names);
                $StaffNames = array_diff($StaffNames, $Names);
            }
?>            <ul class="nobullet" id="poll_options">
<?php       foreach ($Answers as $i => $Answer) { ?>
                <li>
                    <a href="forums.php?action=change_vote&amp;threadid=<?=$threadId?>&amp;auth=<?=$auth?>&amp;vote=<?=(int)$i?>"><?=display_str($Answer == '' ? 'Blank' : $Answer)?></a>
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
            <strong>Votes:</strong> <?=number_format($StaffCount - count($StaffNames))?> / <?=$StaffCount?> current staff, <?=number_format($TotalVotes)?> total
            <br />
            <strong>Missing votes:</strong> <?=implode(", ", $StaffNames); echo "\n";?>
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
    if (check_perms('forums_polls_moderate') && !$RevealVoters) {
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
    [$AuthorID, $Username, $PermissionID, $Paranoia, $Donor, $Warned, $Avatar, $Enabled, $UserTitle] = array_values(Users::user_info($AuthorID));
    $tableClass = ['forum_post', 'wrap_overflow', 'box vertical_margin'];
    if (((!$threadInfo['isLocked'] || $threadInfo['isSticky'])
            && $PostID > $lastRead
            && strtotime($AddedTime) > $user->forumCatchupEpoch()
            ) || (isset($RequestKey) && $Key == $RequestKey)
        ) {
        $tableClass[] = 'forum_unread';
    }
    if (!$user->showAvatars()) {
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
<?php if ($user->showAvatars()) { ?>
        <col class="col_avatar" />
<?php } ?>
        <col class="col_post_body" />
    </colgroup>
    <tr class="colhead_dark">
        <td colspan="<?= $user->showAvatars() ? 2 : 1 ?>">
            <span style="float: left;"><a class="post_id" href="forums.php?action=viewthread&amp;threadid=<?=$threadId?>&amp;postid=<?=$PostID?>#post<?=$PostID?>">#<?=$PostID?></a>
                <?=Users::format_username($AuthorID, true, true, true, true, true, $IsDonorForum) ?>
                <?=time_diff($AddedTime, 2); ?>
                <span id="postcontrol-<?= $PostID ?>">
<?php if (!$threadInfo['isLocked']) { ?>
                - <a href="#quickpost" id="quote_<?=$PostID?>" onclick="Quote('<?=$PostID?>', '<?=$Username?>', true);" title="Select text to quote" class="brackets">Quote</a>
<?php
    }
    if ((!$threadInfo['isLocked'] && $user->writeAccess($forum) && $AuthorID == $user->id()) || check_perms('site_moderate_forums')) {
?>
                - <a href="#post<?=$PostID?>" onclick="Edit_Form('<?=$PostID?>', '<?=$Key?>');" class="brackets">Edit</a>
<?php } ?>
<?php if (check_perms('site_forum_post_delete') && $threadInfo['Posts'] > 1) { ?>
                - <a href="#post<?=$PostID?>" onclick="Delete('<?=$PostID?>');" class="brackets">Delete</a>
<?php
    }
    if ($PostID == $threadInfo['StickyPostID']) { ?>
                <strong><span class="sticky_post_label" class="brackets">Pinned</span></strong>
<?php   if (check_perms('site_moderate_forums')) { ?>
                - <a href="forums.php?action=sticky_post&amp;threadid=<?=$threadId?>&amp;postid=<?=$PostID?>&amp;remove=true&amp;auth=<?=$auth?>" title="Unpin this post" class="brackets tooltip">X</a>
<?php
        }
    } else {
        if (check_perms('site_moderate_forums')) {
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
    if (check_perms('users_warn') && $user->id() != $AuthorID && $user->classLevel() >= $author->classLevel()) {
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
<?php   if ($user->showAvatars()) { ?>
        <td class="avatar" valign="top">
            <?= $userMan->avatarMarkup($user, $author) ?>
        </td>
<?php   } ?>
        <td class="body" valign="top"<?php if (!$user->showAvatars()) { echo ' colspan="2"'; } ?>>
            <div id="content<?=$PostID?>">
                <?= Text::full_format($Body) ?>
<?php   if ($EditedUserID) { ?>
                <br />
                <br />
                <span class="last_edited">
<?php       if (check_perms('site_admin_forums')) { ?>
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
    <?=$threadTitle?>
</div>
<div class="linkbox">
    <?=$Pages?>
</div>
<?php
if (!$threadInfo['isLocked'] || check_perms('site_moderate_forums')) {
    if ($user->writeAccess($forum) && !$LoggedUser['DisablePosting']) {
        View::parse('generic/reply/quickreply.php', [
            'InputTitle' => 'Post reply',
            'InputName' => 'thread',
            'InputID' => $threadId,
            'ForumID' => $forumId,
            'TextareaCols' => 90
        ]);
    }
}
if (count($transitions) > 0 && !check_perms('site_moderate_forums')) {
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
                    <input type="hidden" name="title" value="<?=display_str($threadInfo['Title'])?>" />
                    <input type="hidden" name="transition" value="<?=$transition['id']?>" />
                    <input type="submit" value="<?=$transition['label']?>" />
                </form>
<?php } ?>
            </td>
        </tr>
    </table>
<?php
}
if (check_perms('site_moderate_forums')) {
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
        if (!$user->readAccess($forum)) {
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
<?php } /* foreach */ ?>
                    </optgroup>
                    </select>
                </td>
            </tr>
<?php    if (check_perms('site_admin_forums')) { ?>
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
<?php   if (count($transitions) > 0) { ?>
            <tr>
                <td colspan="2" class="center">
<?php       foreach ($transitions as $transition) { ?>
                    <div style="display: inline-block">
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="action" value="mod_thread" />
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="auth" value="<?=$auth?>" />
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="threadid" value="<?=$threadId?>" />
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="page" value="<?=$Page?>" />
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="title" value="<?=display_str($threadInfo['Title'])?>" />
                        <input form="transition_<?=$transition['id']?>" type="hidden" name="transition" value="<?=$transition['id']?>" />
                        <input form="transition_<?=$transition['id']?>" type="submit" value="<?=$transition['label']?>" />
                    </div>
<?php       } ?>
                </td>
            </tr>
<?php   } ?>
        </table>
    </form>
<?php foreach ($transitions as $transition) { ?>
    <form action="forums.php" method="post" id="transition_<?=$transition['id']?>"></form>
<?php
    }
} // If user is moderator
?>
</div>
<?php
view::show_footer();
