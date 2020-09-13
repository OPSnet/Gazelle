<?php

authorize();

$CollageID = (int)$_POST['collageid'];
if ($CollageID < 1) {
    error(404);
}

[$UserID, $CategoryID] = $DB->row("
    SELECT UserID, CategoryID
    FROM collages
    WHERE ID = ?
    ", $CollageID
);
if ($CategoryID === '0' && $UserID !== $LoggedUser['ID'] && !check_perms('site_collages_delete')) {
    error(403);
}
if ($CategoryID != COLLAGE_ARTISTS_ID) {
    error(403);
}
$collage = new Gazelle\Collage($CollageID);

$ArtistID = (int)$_POST['artistid'];
if ($ArtistID < 1) {
    error(404);
}

if ($_POST['submit'] === 'Remove') {
    $collage->removeArtist($ArtistID);

} elseif (isset($_POST['drag_drop_collage_sort_order'])) {

    @parse_str($_POST['drag_drop_collage_sort_order'], $Series);
    $Series = @array_shift($Series);
    if (is_array($Series)) {
        $SQL = [];
        foreach ($Series as $Sort => $ArtistID) {
            if (is_number($Sort) && is_number($ArtistID)) {
                $Sort = ($Sort + 1) * 10;
                $SQL[] = sprintf('(%d, %d, %d)', $ArtistID, $Sort, $CollageID);
            }
        }

        $SQL = '
            INSERT INTO collages_artists
                (ArtistID, Sort, CollageID)
            VALUES
                ' . implode(', ', $SQL) . '
            ON DUPLICATE KEY UPDATE
                Sort = VALUES (Sort)';

        $DB->prepared_query($SQL);
    }

} else {
    $Sort = $_POST['sort'];
    if (!is_number($Sort)) {
        error(404);
    }
    $DB->prepared_query("
        UPDATE collages_artists
        SET Sort = ?
        WHERE CollageID = ?
            AND ArtistID = ?
        ", $Sort, $CollageID, $ArtistID
    );
}

$Cache->delete_value("collage_$CollageID");
header("Location: collages.php?action=manage_artists&collageid=$CollageID");
