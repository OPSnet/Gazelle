<?php

if (empty($_GET['artistid']) || !is_numeric($_GET['artistid'])) {
    error(404);
}
$ArtistID = intval($_GET['artistid']);

$DB->prepared_query("SELECT
            Name,
            VanityHouse
        FROM artists_group
        WHERE ArtistID = ?", $ArtistID);

if (!$DB->has_results()) {
    error(404);
}

list($Name, $VanityHouseArtist) = $DB->fetch_record();

if ($VanityHouseArtist) {
    $Name .= ' [Vanity House]';
}

View::show_header("Request an Edit: " . $Name);

?>
<div class="thin">
    <div class="header">
        <h2>Request an Edit: <?=display_str($Name)?></h2>
    </div>
    <div class="box pad">
        <div style="margin-bottom: 10px">
            <p><strong class="important_text">You are requesting an edit for...</strong></p>
            <p class="center"><a href="artist.php?id=<?=$ArtistID?>"><?=display_str($Name)?></a></p>
        </div>
        <div style="margin-bottom: 10px">
            <p>
            Please detail all information that needs to be edited for the artist. Include all relevant links (discogs, musicbrainz, etc.).<br /><br />
            This will not generate a report, but will create a thread in the Editing forum.<br /><br />

            What this form can be used for:
            </p>
            <ul>
                <li>Renaming the artist</li>
                <li>Non-redirecting or redirecting aliases</li>
                <li>Adding/Deleting aliases</li>
                <li>etc...</li>
            </ul>
            <p>Do NOT use this form for individual torrents or torrent groups. For individual
                torrents, use the torrent report feature. For torrent groups, go to their respective
                pages and use the edit request feature.</p>
        </div>
        <div>
            <p><strong class="important_text">Edit Details</strong></p>

            <div class="center">
                <form action="artist.php" method="POST">
                    <input type="hidden" name="action" value="takeeditrequest" />
                    <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <textarea name="edit_details" style="width: 95%" required="required"></textarea><br /><br />
                    <input type="submit" value="Submit Edit Request" />
                </form>
            </div>
        </div>
    </div>
</div>

<?php
View::show_footer();
