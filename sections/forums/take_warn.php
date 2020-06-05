<?php
if (!check_perms('users_warn')) {
    error(404);
}
Misc::assert_isset_request($_POST, ['reason', 'privatemessage', 'body', 'length', 'postid', 'userid']);

$UserID = (int)$_POST['userid'];
$UserInfo = Users::user_info($UserID);
if ($UserInfo['Class'] > $LoggedUser['Class']) {
    error(403);
}

$Reason = $_POST['reason'];
$PrivateMessage = $_POST['privatemessage'];
$Body = $_POST['body'];
$WarningLength = $_POST['length'];
$ForumID = (int)$_POST['forumid'];
$PostID = (int)$_POST['postid'];
$Key = (int)$_POST['key'];
$SQLTime = sqltime();

$URL = site_url() . "forums.php?action=viewthread&amp;postid=$PostID#post$PostID";
if ($WarningLength !== 'verbal') {
    $Time = (int)$WarningLength * (7 * 24 * 60 * 60);
    Tools::warn_user($UserID, $Time, "$URL - $Reason");
    $Subject = 'You have received a warning';
    $PrivateMessage = "You have received a $WarningLength week warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;

    $WarnTime = time_plus($Time);
    $AdminComment = date('Y-m-d') . " - Warned until $WarnTime by " . $LoggedUser['Username'] . " for $URL\nReason: $Reason\n\n";

} else {
    $Subject = 'You have received a verbal warning';
    $PrivateMessage = "You have received a verbal warning for [url=$URL]this post[/url].\n\n" . $PrivateMessage;
    $AdminComment = date('Y-m-d') . ' - Verbally warned by ' . $LoggedUser['Username'] . " for $URL\nReason: $Reason\n\n";
    Tools::update_user_notes($UserID, $AdminComment);
}

$user = new \Gazelle\User($UserID);
$user->addForumWarning($AdminComment);

Misc::send_pm($UserID, $LoggedUser['ID'], $Subject, $PrivateMessage);

$forum = new \Gazelle\Forum($ForumID);
list($OldBody, $AuthorID, $TopicID, $ForumID, $IsLocked, $MinClassWrite, $Page) = $forum->postInfo($PostID);

// Perform the update
$forum->editPost($UserID, $PostID, $Body);
$forum->saveEdit($UserID, $PostID, $OldBody);

$CatalogueID = floor((POSTS_PER_PAGE * $Page - POSTS_PER_PAGE) / THREAD_CATALOGUE);
$Cache->begin_transaction("thread_$TopicID" . "_catalogue_$CatalogueID");
if ($Cache->MemcacheDBArray[$Key]['ID'] != $PostID) {
    $Cache->cancel_transaction();
    $Cache->delete_value("thread_$TopicID" . "_catalogue_$CatalogueID");
    //just clear the cache for would be cache-screwer-uppers
} else {
    $Cache->update_row($Key, [
        'ID'           => $Cache->MemcacheDBArray[$Key]['ID'],
        'AuthorID'     => $Cache->MemcacheDBArray[$Key]['AuthorID'],
        'AddedTime'    => $Cache->MemcacheDBArray[$Key]['AddedTime'],
        'Body'         => $Body, //Don't url decode.
        'EditedUserID' => $LoggedUser['ID'],
        'EditedTime'   => $SQLTime,
        'Username'     => $LoggedUser['Username']
    ]);
    $Cache->commit_transaction(3600 * 24 * 5);
}
$ThreadInfo = Forums::get_thread_info($TopicID);
if ($ThreadInfo === null) {
    error(404);
}
if ($ThreadInfo['StickyPostID'] == $PostID) {
    $ThreadInfo['StickyPost']['Body'] = $Body;
    $ThreadInfo['StickyPost']['EditedUserID'] = $LoggedUser['ID'];
    $ThreadInfo['StickyPost']['EditedTime'] = $SQLTime;
    $Cache->cache_value("thread_$TopicID" . '_info', $ThreadInfo, 0);
}

header("Location: forums.php?action=viewthread&postid=$PostID#post$PostID");
