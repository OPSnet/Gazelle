<?php

if (!defined('AJAX')) {
    authorize();
}

if (!empty($LoggedUser['DisableTagging'])) {
    json_or_error('tagging disabled for your account', 403);
}

$UserID = $Viewer->id();
$GroupID = $_REQUEST['groupid'];
$Location = $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$GroupID}";

if (!is_number($GroupID) || !$GroupID) {
    json_or_error('invalid groupid', 0);
}

//Delete cached tag used for undos
if (isset($_REQUEST['undo'])) {
    $Cache->delete_value("deleted_tags_$GroupID".'_'.$UserID);
}

$tagMan = new \Gazelle\Manager\Tag;

$Tags = array_unique(explode(',', $_REQUEST['tagname']));
$Added = [];
$Rejected = [];
foreach ($Tags as $TagName) {
    $TagName = $tagMan->sanitize($TagName);
    $ResolvedTagName = $tagMan->resolve($TagName);

    if (empty($ResolvedTagName)) {
        $Rejected[] = $TagName;
    } else {
        $TagID = $tagMan->create($ResolvedTagName, $UserID);
        if ($tagMan->torrentTagHasVote($TagID, $GroupID, $UserID)) {
            // User has already voted on this tag
            if (defined('AJAX')) {
                json_error('you have already voted on this tag');
            } else {
                header("Location: $Location");
            }
            exit;
        }
        $tagMan->createTorrentTag($TagID, $GroupID, $UserID, 3);
        $tagMan->createTorrentTagVote($TagID, $GroupID, $UserID, 'up');

        (new Gazelle\Log)->group($GroupID, $UserID, "Tag \"$ResolvedTagName\" added to group");
        $Added[] = $ResolvedTagName;
    }
}

Torrents::update_hash($GroupID); // Delete torrent group cache
if (defined('AJAX')) {
    json_print('success', [
        'added' => $Added,
        'rejected' => $Rejected,
    ]);
} else {
    header("Location: $Location");
}
