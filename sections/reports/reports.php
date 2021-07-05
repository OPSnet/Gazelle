<?php

if (!$Viewer->permitted('admin_reports') && !$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;

$cond = [];
$args = [];
if (isset($_GET['id'])) {
    $View = 'Single report';
    $cond[] = 'r.ID = ?';
    $args[] = (int)$_GET['id'];
} elseif (empty($_GET['view'])) {
    $View = 'New';
    $cond[] = 'r.Status = ?';
    $args[] = 'New';
} else {
    $View = $_GET['view'];
    switch ($_GET['view']) {
        case 'old':
            $cond[] = 'r.Status = ?';
            $args[] = 'Resolved';
            break;
        default:
            error(403);
            break;
    }
}

if (!$Viewer->permitted('admin_reports') && $Viewer->permitted('site_moderate_forums')) {
    $cond[] = "r.Type IN ('comment', 'post', 'thread')";
}

$Where = $cond ? ('WHERE ' . implode(' AND ', $cond))
    : '';

$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($DB->scalar("SELECT count(*) FROM reports r $Where", ...$args));
array_push($args, $paginator->limit(), $paginator->offset());

$DB->prepared_query("
    SELECT
        r.ID,
        r.UserID,
        um.Username,
        r.ThingID,
        r.Type,
        r.ReportedTime,
        r.Reason,
        r.Status,
        r.ClaimerID,
        r.Notes,
        r.ResolverID
    FROM reports AS r
    INNER JOIN users_main AS um ON r.UserID = um.ID
    $Where
    ORDER BY ReportedTime DESC
    LIMIT ? OFFSET ?
    ", ...$args
);
$Reports = $DB->get_query_id();

View::show_header('Reports', 'bbcode,reports');
?>
<div class="thin">
    <div class="header">
        <h2>Active Reports</h2>
        <div class="linkbox">
            <a href="reports.php">New</a> |
            <a href="reports.php?view=old">Old</a> |
            <a href="reports.php?action=stats">Stats</a>
        </div>
    </div>
<?= $paginator->linkbox() ?>
<?php
require_once('array.php');
$DB->set_query_id($Reports);
while ([$ReportID, $UserID, $UserName, $ThingID, $Short, $ReportedTime, $Reason, $Status, $ClaimerID, $Notes, $ResolverID] = $DB->next_record(MYSQLI_NUM, false)) {
    $Type = $Types[$Short];
    $Reference = "reports.php?id=$ReportID#report$ReportID";
?>
        <div id="report_<?=$ReportID?>" style="margin-bottom: 1em;" class="pending_report_v1">
            <table cellpadding="5" id="report_<?=$ReportID?>">
                <tr>
                    <td><strong><a href="<?=$Reference?>">Report #<?=$ReportID?></a></strong></td>
                    <td>
                        <strong><?=$Type['title']?></strong> was reported by <a href="user.php?id=<?=$UserID?>"><?=$UserName?></a> <?=time_diff($ReportedTime)?>
                        <a href="reports.php?action=compose&amp;toid=<?=$UserID?>&amp;reportid=<?=$ReportID?>&amp;type=<?=$Short?>&amp;thingid=<?=$ThingID?>" class="brackets">Contact</a>
                    </td>
                </tr>
                <tr>
                    <td class="center" colspan="2">
                        <strong>
<?php                       switch ($Short) {
                                case 'user':
                                    $Username = $DB->scalar("
                                        SELECT Username FROM users_main WHERE ID = ?
                                        ", $ThingID
                                    );
                                    if (!$Username) {
                                        echo 'No user with the reported ID found';
                                    } else {
                                        echo "<a href=\"user.php?id=$ThingID\">" . display_str($Username) . '</a>';
                                    }
                                    break;
                                case 'request':
                                case 'request_update':
                                    $Name = $DB->scalar("
                                        SELECT Title FROM requests WHERE ID = ?
                                        ", $ThingID
                                    );
                                    if (!$Name) {
                                        echo 'No request with the reported ID found';
                                    } else {
                                        echo "<a href=\"requests.php?action=view&amp;id=$ThingID\">" . display_str($Name) . '</a>';
                                    }
                                    break;
                                case 'collage':
                                    $Name = $DB->scalar("
                                        SELECT Name FROM collages WHERE ID = ?
                                        ", $ThingID
                                    );
                                    if (!$Name) {
                                        echo 'No collage with the reported ID found';
                                    } else {
                                        echo "<a href=\"collages.php?id=$ThingID\">" . display_str($Name) . '</a>';
                                    }
                                    break;
                                case 'thread':
                                    [$forumId, $forumName, $thread, $userId, $username] = $DB->row("
                                        SELECT
                                            f.id,
                                            f.Name,
                                            ft.Title,
                                            um.ID,
                                            um.Username
                                        FROM forums f
                                        INNER JOIN forums_topics ft ON (ft.ForumID = f.ID)
                                        INNER JOIN users_main um on (um.ID = ft.AuthorID)
                                        WHERE ft.ID = ?
                                        ", $ThingID
                                    );
                                    if (!$thread) {
                                        echo 'No forum thread with the reported ID found';
                                    } else { ?>
<a href="forums.php?action=viewforum&amp;forumid=<?= $forumId ?>"><?= $forumName
    ?></a> &rsaquo; <a href="forums.php?action=viewthread&amp;threadid=<?= $ThingID ?>"><?=
    display_str($thread) ?></a> created by <a href="user.php?id=<?= $userId ?>"><?= $username ?></a>
<?php
                                    }
                                    break;
                                case 'post':
                                    $PerPage = $Viewer->postsPerPage();
                                    [$forumId, $forumName, $threadId, $threadName, $userId, $username, $PostID, $Body, $PostNum] = $DB->row("
                                        SELECT
                                            f.ID,
                                            f.Name,
                                            ft.ID,
                                            ft.Title,
                                            um.ID,
                                            um.Username,
                                            p.ID,
                                            p.Body,
                                            (
                                                SELECT count(*)
                                                FROM forums_posts AS p2
                                                WHERE p2.TopicID = p.TopicID AND p2.ID <= p.ID
                                            ) AS PostNum
                                        FROM forums_posts AS p
                                        INNER JOIN forums_topics ft ON (ft.ID = p.TopicID)
                                        INNER JOIN forums f on (f.ID = ft.ForumID)
                                        INNER JOIN users_main um on (um.ID = p.AuthorID)
                                        WHERE p.ID = ?
                                        ", $ThingID
                                    );
                                    if (!$PostID) {
                                        echo 'No forum post with the reported ID found';
                                    } else { ?>
<a href="forums.php?action=viewforum&amp;forumid=<?= $forumId ?>"><?= $forumName
    ?></a> &rsaquo; <a href="forums.php?action=viewthread&amp;threadid=<?= $threadId ?>"><?=
    display_str($threadName) ?></a> &rsaquo; <a href="forums.php?action=viewthread&amp;threadid=<?=
    $threadId ?>&amp;post=<?= $PostNum ?>#post<?= $PostID ?>">Post #<?= $PostID ?></a> by
    <a href="user.php?id=<?= $userId ?>"><?= $username ?></a>
<?php
                                    }
                                    break;
                                case 'comment':
                                    $DB->prepared_query("
                                        SELECT 1 FROM comments WHERE ID = ?
                                        ", $ThingID
                                    );
                                    if (!$DB->has_results()) {
                                        echo 'No comment with the reported ID found';
                                    } else {
                                        echo "<a href=\"comments.php?action=jump&amp;postid=$ThingID\">COMMENT</a>";
                                    }
                                    break;
                            }
                            ?>
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><?=Text::full_format($Reason)?></td>
                </tr>
                <tr>
                    <td colspan="2">
<?php               if ($ClaimerID == $Viewer->id()) { ?>
                        <span id="claimed_<?=$ReportID?>">Claimed by <?=Users::format_username($ClaimerID, false, false, false, false)?> <a href="#" onclick="unClaim(<?=$ReportID?>); return false;" class="brackets">Unclaim</a></span>
<?php               } elseif ($ClaimerID) { ?>
                        <span id="claimed_<?=$ReportID?>">Claimed by <?=Users::format_username($ClaimerID, false, false, false, false)?></span>
<?php               } else { ?>
                        <a href="#" id="claim_<?=$ReportID?>" onclick="claim(<?=$ReportID?>); return false;" class="brackets">Claim</a>
<?php               } ?>
                        &nbsp;&nbsp;
                        <a href="#" onclick="toggleNotes(<?=$ReportID?>); return false;" class="brackets">Toggle notes</a>

                        <div id="notes_div_<?=$ReportID?>" style="display: <?=empty($Notes) ? 'none' : 'block'; ?>;">
                            <textarea cols="50" rows="3" id="notes_<?=$ReportID?>"><?=$Notes?></textarea>
                            <br />
                            <input type="submit" onclick="saveNotes(<?=$ReportID?>)" value="Save" />
                        </div>
                    </td>
                </tr>
<?php       if ($Status != 'Resolved') { ?>
                <tr>
                    <td class="center" colspan="2">
                        <form id="report_form_<?=$ReportID?>" action="">
                            <input type="hidden" name="reportid" value="<?=$ReportID?>" />
                            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                            <input type="submit" onclick="return resolve(<?=$ReportID?>, <?=(($ClaimerID == $Viewer->id() || !$ClaimerID) ? 'true' : 'false')?>)" name="submit" value="Resolve" />
                        </form>
                    </td>
                </tr>
<?php       } else { ?>
                <tr>
                    <td colspan="2">
                        Resolved by <a href="users.php?id=<?=$ResolverID?>"><?= $userMan->findById($ResolverID)->username() ?></a>
                    </td>
                </tr>
<?php       } ?>
            </table>
        </div>
<?php
        $DB->set_query_id($Reports);
    }
?>
<?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
