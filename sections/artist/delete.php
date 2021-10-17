<?php
/************************************************************************
||------------|| Delete artist ||--------------------------------------||

This is a very powerful page - it deletes an artist, and all associated
requests and torrents. It is called when $_GET['action'] == 'delete'.

************************************************************************/

if (!$Viewer->permitted('site_delete_artist') || !$Viewer->permitted('torrents_delete')) {
    error(403);
}
authorize();

$ArtistID = (int)$_GET['artistid'];
if (!$ArtistID) {
    error(404);
}

View::show_header('Artist deleted');

$Name = $DB->scalar("
    SELECT Name
    FROM artists_group
    WHERE ArtistID = ?
    ", $ArtistID
);

$DB->prepared_query("
    SELECT tg.Name, tg.ID
    FROM torrents_group AS tg
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
    WHERE ta.ArtistID = ?
    ", $ArtistID
);
$Count = $DB->record_count();
if ($DB->has_results()) {
?>
    <div class="thin">
        There are still torrents that have <a href="artist.php?id=<?=$ArtistID?>" class="tooltip" title="View artist" dir="ltr"><?=$Name?></a> as an artist.<br />
        Please remove the artist from these torrents manually before attempting to delete.<br />
        <div class="box pad">
            <ul>
<?php while ([$GroupName, $GroupID] = $DB->next_record(MYSQLI_NUM, true)) { ?>
                <li>
                    <a href="torrents.php?id=<?=$GroupID?>" class="tooltip" title="View torrent group" dir="ltr"><?=$GroupName?></a>
                </li>
<?php } ?>
            </ul>
        </div>
    </div>
<?php
}

$DB->prepared_query("
    SELECT r.Title, r.ID
    FROM requests AS r
    LEFT JOIN requests_artists AS ra ON (ra.RequestID = r.ID)
    WHERE ra.ArtistID = ?
    ", $ArtistID
);
$Count += $DB->record_count();
if ($DB->has_results()) {
?>
    <div class="thin">
        There are still requests that have <a href="artist.php?id=<?=$ArtistID?>" class="tooltip" title="View artist" dir="ltr"><?=$Name?></a> as an artist.<br />
        Please remove the artist from these requests manually before attempting to delete.<br />
        <div class="box pad">
            <ul>
<?php while ([$RequestName, $RequestID] = $DB->next_record(MYSQLI_NUM, true)) { ?>
                <li>
                    <a href="requests.php?action=view&amp;id=<?=$RequestID?>" class="tooltip" title="View request" dir="ltr"><?=$RequestName?></a>
                </li>
<?php } ?>
            </ul>
        </div>
    </div>
<?php
}

if ($Count == 0) {
    Artists::delete_artist($ArtistID);
?>
    <div class="thin box pad">
        Artist "<?=$Name?>" deleted!
    </div>
<?php
}
View::show_footer();
