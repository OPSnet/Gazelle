<?php
authorize();
if (!empty($LoggedUser['DisableTagging'])) {
    error(403);
}

$UserID = $LoggedUser['ID'];
$GroupID = $_POST['groupid'];
$Location = $_SERVER['HTTP_REFERER'] ?? "torrents.php?id={$GroupID}";

if (!is_number($GroupID) || !$GroupID) {
    error(0);
}

//Delete cached tag used for undos
if (isset($_POST['undo'])) {
    $Cache->delete_value("deleted_tags_$GroupID".'_'.$UserID);
}

$tagMan = new \Gazelle\Manager\Tag;

$Tags = array_unique(explode(',', $_POST['tagname']));
foreach ($Tags as $TagName) {
    $TagName = $tagMan->resolve($tagMan->sanitize($TagName));

    if (!empty($TagName)) {
        $TagID = $tagMan->create($TagName, $UserID);
        if ($tagMan->torrentTagHasVote($TagID, $GroupID, $UserID)) {
            // User has already voted on this tag
            header("Location: $Location");
            exit;
        }
        $tagMan->createTorrentTag($TagID, $GroupID, $UserID, 3);
        $tagMan->createTorrentTagVote($TagID, $GroupID, $UserID, 'up');

        (new Gazelle\Log)->group($GroupID, $UserID, "Tag \"$TagName\" added to group");
    }
}

Torrents::update_hash($GroupID); // Delete torrent group cache
header("Location: $Location");
