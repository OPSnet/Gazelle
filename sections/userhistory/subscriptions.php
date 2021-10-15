<?php

$userMan = new Gazelle\Manager\User;

$showAvatars   = $Viewer->showAvatars();
$showCollapsed = (bool)($_GET['collapse'] ?? true);
$showUnread    = (bool)($_GET['showunread'] ?? true);

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$forMan = new Gazelle\Manager\Forum;
$commMan = new Gazelle\Manager\Comment;
if ($showUnread) {
    $total = $forMan->unreadSubscribedForumTotal($Viewer)
        + $commMan->unreadSubscribedCommentTotal($Viewer);
} else {
    $total = $forMan->subscribedForumTotal($Viewer)
        + $commMan->subscribedCommentTotal($Viewer);
}
$paginator->setTotal($total);

$Results = (new Gazelle\Manager\Subscription)->latestSubscriptionList($Viewer, $showUnread, $paginator->limit(), $paginator->offset());

$TorrentGroups = $Requests = [];
foreach ($Results as $Result) {
    if ($Result['Page'] == 'torrents') {
        $TorrentGroups[] = $Result['PageID'];
    } elseif ($Result['Page'] == 'requests') {
        $Requests[] = $Result['PageID'];
    }
}

$TorrentGroups = Torrents::get_groups($TorrentGroups, true, true, false);
$Requests = Requests::get_requests($Requests);

View::show_header('Subscriptions', ['js' => 'subscriptions,comments,bbcode']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $Viewer->link() ?> ?></a> &rsaquo; Subscriptions<?=$showUnread ? ' with unread posts'
            . ($paginator->total() ? ' (' . $paginator->total() . ' new)' : '') : ''?></h2>
        <div class="linkbox">
<?php if (!$showUnread) { ?>
            <br /><br />
            <a href="userhistory.php?action=subscriptions&amp;showunread=1" class="brackets">Only display subscriptions with unread replies</a>&nbsp;
<?php } else { ?>
            <br /><br />
            <a href="userhistory.php?action=subscriptions&amp;showunread=0" class="brackets">Show all subscriptions</a>&nbsp;
<?php
}
if ($paginator->total()) {
?>
            <a href="#" onclick="Collapse(); return false;" id="collapselink" class="brackets"><?=$showCollapsed ? 'Show' : 'Hide' ?> post bodies</a>&nbsp;
<?php } ?>
            <a href="userhistory.php?action=posts&amp;userid=<?=$Viewer->id()?>" class="brackets">Go to post history</a>&nbsp;
            <a href="userhistory.php?action=quote_notifications" class="brackets">Quote notifications</a>&nbsp;&nbsp;&nbsp;
            <a href="userhistory.php?action=catchup&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a>
        </div>
    </div>
<?php if (!$paginator->total()) { ?>
    <div class="center">
        No subscriptions<?=$showUnread ? ' with unread posts' : ''?>
    </div>
<?php } else {
    echo $paginator->linkbox();
    foreach ($Results as $Result) {
        switch ($Result['Page']) {
            case 'artist':
                $Links = 'Artist: <a href="artist.php?id=' . $Result['PageID'] . '">' . display_str($Result['Name']) . '</a>';
                $JumpLink = 'artist.php?id=' . $Result['PageID'] . '&amp;postid=' . $Result['PostID'] . '#post' . $Result['PostID'];
                break;
            case 'collages':
                $Links = 'Collage: <a href="collages.php?id=' . $Result['PageID'] . '">' . display_str($Result['Name']) . '</a>';
                $JumpLink = 'collages.php?action=comments&collageid=' . $Result['PageID'] . '&amp;postid=' . $Result['PostID'] . '#post' . $Result['PostID'];
                break;
            case 'requests':
                if (isset($Requests[$Result['PageID']])) {
                    $Request = $Requests[$Result['PageID']];
                    $CategoryName = CATEGORY[$Request['CategoryID'] - 1];

                    $Links = 'Request: ';
                    if ($CategoryName == 'Music' || $CategoryName == 'Audiobooks' || $CategoryName == 'Comedy') {
                        $Links .= ($CategoryName == 'Music' ? Artists::display_artists(Requests::get_artists($Result['PageID'])) : '')
                            . '<a href="requests.php?action=view&amp;id=' . $Result['PageID'] . '" dir="ltr">' . $Request['Title'] . " [" . $Request['Year'] . "]</a>";
                    } else {
                        $Links .= '<a href="requests.php?action=view&amp;id=' . $Result['PageID'] . '">' . $Request['Title'] . "</a>";
                    }
                    $JumpLink = 'requests.php?action=view&amp;id=' . $Result['PageID'] . '&amp;postid=' . $Result['PostID'] . '#post' . $Result['PostID'];
                }
                break;
            case 'torrents':
                if (isset($TorrentGroups[$Result['PageID']])) {
                    $GroupInfo = $TorrentGroups[$Result['PageID']];
                    $Links = 'Torrent: ' . Artists::display_artists($GroupInfo['ExtendedArtists']) . '<a href="torrents.php?id=' . $GroupInfo['ID'] . '" dir="ltr">' . $GroupInfo['Name'] . '</a>';
                    if ($GroupInfo['Year'] > 0) {
                        $Links .= " [" . $GroupInfo['Year'] . "]";
                    }
                    if ($GroupInfo['ReleaseType'] > 0) {
                        $Links .= " [" . (new Gazelle\ReleaseType)->findNameById($GroupInfo['ReleaseType']) . "]";
                    }
                    $JumpLink = 'torrents.php?id=' . $GroupInfo['ID'] . '&amp;postid=' . $Result['PostID'] . '#post' . $Result['PostID'];
                }
                break;
            case 'forums':
                $Links = 'Forums: <a href="forums.php?action=viewforum&amp;forumid=' . $Result['ForumID'] . '">' . display_str($Result['ForumName']) . '</a> &rsaquo; ' .
                    '<a href="forums.php?action=viewthread&amp;threadid=' . $Result['PageID'] .
                        '" class="tooltip" title="' . display_str($Result['Name']) . '">' .
                        display_str(shortenString($Result['Name'], 75)) .
                    '</a>';
                $JumpLink = "forums.php?action=viewthread&amp;threadid={$Result['PageID']}"
                    . ($Result['PostID'] ? "&amp;postid={$Result['PostID']}#post{$Result['PostID']}" : '');
                break;
            default:
                error(0);
        }
?>
    <table class="forum_post box vertical_margin<?=(!$showAvatars ? ' noavatar' : '')?>">
        <colgroup>
<?php   if ($showAvatars) { ?>
            <col class="col_avatar" />
<?php   } ?>
            <col class="col_post_body" />
        </colgroup>
        <tr class="colhead_dark notify_<?=$Result['Page']?>">
            <td colspan="<?= $showAvatars ? 2 : 1 ?>">
                <span style="float: left;">
                    <?=$Links . ($Result['PostID'] < $Result['LastPost'] ? ' <span class="new">(New!)</span>' : '')?>
                </span>
                <span style="float: left;" class="tooltip last_read" title="Jump to last read">
                    <a href="<?=$JumpLink?>"></a>
                </span>
<?php   if ($Result['Page'] == 'forums') { ?>
                <span id="bar<?=$Result['PostID'] ?>" style="float: right;">
                    <a href="#" onclick="Subscribe(<?=$Result['PageID']?>); return false;" id="subscribelink<?=$Result['PageID']?>" class="brackets">Unsubscribe</a>
<?php   } else { ?>
                <span id="bar_<?=$Result['Page'] . $Result['PostID'] ?>" style="float: right;">
                    <a href="#" onclick="SubscribeComments('<?=$Result['Page']?>', <?=$Result['PageID']?>); return false;" id="subscribelink_<?=$Result['Page'] . $Result['PageID']?>" class="brackets">Unsubscribe</a>
<?php   } ?>
                    &nbsp;
                    <a href="#">&uarr;</a>
                </span>
            </td>
        </tr>
<?php   if (!empty($Result['LastReadBody'])) { /* if a user is subscribed to a topic/comments but hasn't accessed the site ever, LastReadBody will be null - in this case we don't display a post. */ ?>
        <tr class="row<?=$showCollapsed ? ' hidden' : '' ?>">
<?php       if ($showAvatars) { ?>
            <td class="avatar" valign="top">
                <?= $userMan->avatarMarkup($Viewer, new Gazelle\User($Result['LastReadUserID'])) ?>
            </td>
<?php       } ?>
            <td class="body" valign="top">
                <div class="content3">
                    <?=Text::full_format($Result['LastReadBody']) ?>
<?php       if ($Result['LastReadEditedUserID']) { ?>
                    <br /><br />
                    <span class="last_edited">Last edited by <?=Users::format_username($Result['LastReadEditedUserID'], false, false, false) ?> <?=time_diff($Result['LastReadEditedTime'])?></span>
<?php       } ?>
                </div>
            </td>
        </tr>
<?php   } ?>
    </table>
<?php
    }
    echo $paginator->linkbox();
}
?>
</div>
<?php
View::show_footer();
