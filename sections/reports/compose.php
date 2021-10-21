<?php
if (!$Viewer->permitted('site_moderate_forums')) {
    error(403);
}

if (empty($Return)) {
    $ToID = (int)$_GET['toid'];
    if ($ToID === $Viewer->id()) {
        error("You cannot start a conversation with yourself!");
        header('Location: inbox.php');
    }
}

if (!$ToID) {
    error(404);
}

$ReportID = (int)$_GET['reportid'];
$ThingID = (int)$_GET['thingid'];
$Type = $_GET['type'];

if (!$ReportID || !$ThingID || !$Type) {
    error(403);
}

if ($Viewer->disablePm() && !isset($StaffIDs[$ToID])) {
    error(403);
}

$user = new Gazelle\User($ToID);
$ComposeToUsername = $user->username();
if (!$ComposeToUsername) {
    error(404);
}
View::show_header('Compose', ['js' => 'inbox,bbcode']);

// $TypeLink is placed directly in the <textarea> when composing a PM
switch ($Type) {
    case 'user':
        $Name = $DB->scalar("
            SELECT Username FROM users_main WHERE ID = ?
            ", $ThingID
        );
        if (!$Name) {
            error('No user with the reported ID found');
        } else {
            $TypeLink = "the user [user]{$Name}[/user]";
            $Subject = 'User Report: '.display_str($Name);
        }
        break;
    case 'request':
    case 'request_update':
        $Name = $DB->scalar("
            SELECT Title FROM requests WHERE ID = ?
            ", $ThingID
        );
        if (!$Name) {
            error('No request with the reported ID found');
        } else {
            $TypeLink = "the request [url=requests.php?action=view&amp;id=$ThingID]".display_str($Name).'[/url]';
            $Subject = 'Request Report: '.display_str($Name);
        }
        break;
    case 'collage':
        $Name = $DB->scalar("
            SELECT Name FROM collages WHERE ID = ?
            ", $ThingID
        );
        if (!$Name) {
            error('No collage with the reported ID found');
        } else {
            $TypeLink = "the collage [url=collage.php?id=$ThingID]".display_str($Name).'[/url]';
            $Subject = 'Collage Report: '.display_str($Name);
        }
        break;
    case 'thread':
        $Name = $DB->scalar("
            SELECT Title FROM forums_topics WHERE ID = ?
            ", $ThingID
        );
        if (!$Name) {
            error('No forum thread with the reported ID found');
        } else {
            $TypeLink = "the forum thread [url=forums.php?action=viewthread&amp;threadid=$ThingID]".display_str($Name).'[/url]';
            $Subject = 'Forum Thread Report: '.display_str($Name);
        }
        break;
    case 'post':
        $PerPage = $Viewer->postsPerPage();
        [$PostID, $Body, $TopicID, $PostNum] = $DB->row("
            SELECT p.ID,
                p.Body,
                p.TopicID,
                (
                    SELECT count(*)
                    FROM forums_posts AS p2
                    WHERE p2.TopicID = p.TopicID
                        AND p2.ID <= p.ID
                ) AS PostNum
            FROM forums_posts AS p
            WHERE p.ID = ?
            ", $ThingID
        );
        if (!$PostID) {
            error('No forum post with the reported ID found');
        } else {
            $TypeLink = "this [url=forums.php?action=viewthread&amp;threadid=$TopicID&amp;post=$PostNum#post$PostID]forum post[/url]";
            $Subject = 'Forum Post Report: Post ID #'.display_str($PostID);
        }
        break;
    case 'comment':
        $Body = $DB->scalar("
            SELECT Body FROM comments WHERE ID = ?
            ", $ThingID
        );
        if (!$Body) {
            error('No comment with the reported ID found');
        } else {
            $TypeLink = "[url=comments.php?action=jump&amp;postid=$ThingID]this comment[/url]";
            $Subject = 'Comment Report: ID #' . display_str($ThingID) . " " . shortenString($Body, 200);
        }
        break;
    default:
        error('Incorrect type');
        break;
}

$Body = "You reported $TypeLink for the reason:\n[quote]"
    . $DB->scalar("
        SELECT Reason FROM reports WHERE ID = ?
        ", $ReportID
    ) . '[/quote]';

?>
<div class="thin">
    <div class="header">
        <h2>
            Send a message to <a href="user.php?id=<?=$ToID?>"> <?=$ComposeToUsername?></a>
        </h2>
    </div>
    <form class="send_form" name="message" action="reports.php" method="post" id="messageform">
        <div class="box pad">
            <input type="hidden" name="action" value="takecompose" />
            <input type="hidden" name="toid" value="<?=$ToID?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <div id="quickpost">
                <h3>Subject</h3>
                <input type="text" name="subject" size="95" value="<?=(!empty($Subject) ? $Subject : '')?>" />
                <br />
                <h3>Body</h3>
                <textarea id="body" name="body" cols="95" rows="10"><?=(!empty($Body) ? $Body : '')?></textarea>
            </div>
            <div id="preview" class="hidden"></div>
            <div id="buttons" class="center">
                <input type="button" value="Preview" onclick="Quick_Preview();" />
                <input type="submit" value="Send message" />
            </div>
        </div>
    </form>
</div>
<?php
View::show_footer();
