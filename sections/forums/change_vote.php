<?php
authorize();
$ThreadID = (int)$_GET['threadid'];
$NewVote = (int)$_GET['vote'];

if (!$ThreadID || !$NewVote) {
    error(404);
}

if (!check_perms('site_moderate_forums')) {
    $ForumID = $DB->scalar("
        SELECT ForumID
        FROM forums_topics
        WHERE ID = ?
        ", $ThreadID
    );
    if (!in_array($ForumID, $ForumsRevealVoters)) {
        error(403);
    }
}

$DB->prepared_query("
    UPDATE forums_polls_votes SET
        Vote = ?
    WHERE TopicID = ?
        AND UserID = ?
    ", $NewVote, $ThreadID, $LoggedUser['ID']
);
$Cache->delete_value('polls_'.$ThreadID);
header("Location: forums.php?action=viewthread&threadid=".$ThreadID);
