<?php

$userMan = new Gazelle\Manager\User;
if (!isset($_GET['userid'])) {
    $User = $Viewer;
    $ownProfile = true;
} else {
    $User = $userMan->findById((int)($_GET['userid'] ?? 0));
    if (is_null($User)) {
        error(404);
    }
    $ownProfile = $User->id() == $Viewer->id();
    if (!$ownProfile && !$Viewer->permitted('users_override_paranoia')) {
        error(403);
    }
}
$UserID   = $User->id();
$Username = $User->username();

$DB->prepared_query("
    SELECT ag.ArtistID, ag.Name
    FROM bookmarks_artists AS ba
    INNER JOIN artists_group AS ag USING (ArtistID)
    WHERE ba.UserID = ?
    ORDER BY ag.Name
    ", $UserID
);
$ArtistList = $DB->to_array();

$Title = "$Username &rsaquo; Bookmarked artists";

if ($Viewer->permitted('site_torrents_notify')) {
    if (($Notify = $Cache->get_value('notify_artists_' . $Viewer->id() )) === false) {
        $DB->prepared_query("
            SELECT ID, Artists
            FROM users_notify_filters
            WHERE Label = 'Artist notifications'
                AND UserID = ?
            LIMIT 1
            ", $Viewer->id()
        );
        $Notify = $DB->next_record(MYSQLI_ASSOC);
        $Cache->cache_value('notify_artists_' . $Viewer->id(), $Notify, 0);
    }
}

View::show_header($Title, ['js' => 'browse']);
?>
<div class="thin">
    <div class="header">
        <h2><?=$Title?></h2>
        <div class="linkbox">
            <a href="bookmarks.php?type=torrents" class="brackets">Torrents</a>
            <a href="bookmarks.php?type=artists" class="brackets">Artists</a>
            <a href="bookmarks.php?type=collages" class="brackets">Collages</a>
            <a href="bookmarks.php?type=requests" class="brackets">Requests</a>
        </div>
    </div>
    <div class="box pad" align="center">
<?php
if (count($ArtistList) === 0) { ?>
        <h2>No bookmarked artists</h2>
    </div>
</div>
<?php
    View::show_footer();
    exit;
}
?>
    <table width="100%" class="artist_table">
        <tr class="colhead">
            <td>Artist</td>
        </tr>
<?php
$Row = 'a';
foreach ($ArtistList as $Artist) {
    $Row = $Row === 'a' ? 'b' : 'a';
    [$ArtistID, $Name] = $Artist;
?>
        <tr class="row<?=$Row?> bookmark_<?=$ArtistID?>">
            <td>
                <a href="artist.php?id=<?=$ArtistID?>"><?=$Name?></a>
                <span style="float: right;">
<?php
    if ($Viewer->permitted('site_torrents_notify')) {
        if (stripos($Notify['Artists'], "|$Name|") === false) {
?>
                    <a href="artist.php?action=notify&amp;artistid=<?=$ArtistID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Notify of new uploads</a>
<?php } else { ?>
                    <a href="artist.php?action=notifyremove&amp;artistid=<?=$ArtistID?>&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Do not notify of new uploads</a>
<?php
        }
    }
    if ($ownProfile) {
?>
                    <a href="#" id="bookmarklink_artist_<?=$ArtistID?>" onclick="Unbookmark('artist', <?=$ArtistID?>, 'Bookmark'); return false;" class="brackets">Remove bookmark</a>
<?php } ?>
                </span>
            </td>
        </tr>
<?php } ?>
    </table>
    </div>
</div>
<?php
$Cache->cache_value('bookmarks_'.$UserID, serialize([[$Username, $TorrentList, $CollageDataList]]), 3600);
View::show_footer();
