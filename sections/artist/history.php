<?php

$ArtistID = (int)$_GET['artistid'];
if (!$ArtistID) {
    error(404);
}

$Name = $DB->scalar("
    SELECT Name
    FROM artists_group
    WHERE ArtistID = ?
    ", $ArtistID
);
if (!$Name) {
    error(404);
}

View::show_header("Revision history for $Name");
?>
<div class="thin">
    <div class="header">
        <h2>Revision history for <a href="artist.php?id=<?=$ArtistID?>"><?=$Name?></a></h2>
    </div>
<?php
RevisionHistoryView::render_revision_history(RevisionHistory::get_revision_history('artists', $ArtistID), "artist.php?id=$ArtistID");
?>
</div>
<?php
View::show_footer();
