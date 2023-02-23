<?php

use Gazelle\Util\Irc;

authorize();

$ID = (int)$_POST['id'];
if (!$ID || empty($_POST['type']) || ($_POST['type'] !== 'request_update' && empty($_POST['reason']))) {
    error(404);
}

require_once('array.php');
if (!array_key_exists($_POST['type'], $Types)) {
    error(403);
}
$Short = $_POST['type'];
$Type = $Types[$Short];

if ($Short !== 'request_update') {
    $Reason = $_POST['reason'];
} else {
    $Year = trim($_POST['year']);
    if (empty($Year) || !is_number($Year)) {
        error('Year must be specified.');
    }
    $Reason = '[b]Year[/b]: '.$Year.".\n\n";
    // If the release type is somehow invalid, return "Not given"; otherwise, return the release type.
    $Reason .= '[b]Release type[/b]: '.((empty($_POST['releasetype']) || !is_number($_POST['releasetype']) || $_POST['releasetype'] === '0')
        ? 'Not given' : (new Gazelle\ReleaseType)->findNameById($_POST['releasetype']))." . \n\n";
    $Reason .= '[b]Additional comments[/b]: '.$_POST['comment'];
}

$db = Gazelle\DB::DB();
switch ($Short) {
    case 'request':
    case 'request_update':
        $Link = "requests.php?action=view&id=$ID";
        break;
    case 'user':
        $Link = "user.php?id=$ID";
        break;
    case 'collage':
        $Link = "collages.php?id=$ID";
        break;
    case 'thread':
        $Link = "forums.php?action=viewthread&threadid=$ID";
        break;
    case 'post':
        [$PostID, $TopicID, $PostNum] = $db->row("
            SELECT p.ID,
                p.TopicID,
                (
                    SELECT count(p2.ID)
                    FROM forums_posts AS p2
                    WHERE p2.TopicID = p.TopicID
                        AND p2.ID <= p.ID
                ) AS PostNum
            FROM forums_posts AS p
            WHERE p.ID = ?
            ", $ID
        );
        $Link = "forums.php?action=viewthread&threadid=$TopicID&post=$PostNum#post$PostID";
        break;
    case 'comment':
        $Link = "comments.php?action=jump&postid=$ID";
        break;
}

$db->prepared_query('
    INSERT INTO reports
           (UserID, ThingID, Type, Reason)
    VALUES (?,      ?,       ?,    ?)
    ', $Viewer->id(), $ID, $Short, $Reason
);
$ReportID = $db->inserted_id();

$Channels = [];

if ($Short === 'request_update') {
    $Channels[] = '#requestedits';
    $Cache->increment('num_update_reports');
}
if (in_array($Short, ['comment', 'post', 'thread'])) {
    $Channels[] = '#forumreports';
}

foreach ($Channels as $Channel) {
    Irc::sendMessage($Channel, "$ReportID - " . $Viewer->username() . " just reported a $Short: " . SITE_URL . "/$Link : " . strtr($Reason, "\n", ' '));
}

$Cache->delete_value('num_other_reports');

header("Location: $Link");
