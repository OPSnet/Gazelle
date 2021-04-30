<?php

if (empty($_GET['groupid']) || !is_numeric($_GET['groupid'])) {
    error(404);
}
$GroupID = intval($_GET['groupid']);

$TorrentCache = get_group_info($GroupID);
$TorrentDetails = $TorrentCache[0];
$TorrentList = $TorrentCache[1];

// Group details
list($WikiBody, $WikiImage, $GroupID, $GroupName, $GroupYear,
    $GroupRecordLabel, $GroupCatalogueNumber, $ReleaseType, $GroupCategoryID,
    $GroupTime, $GroupVanityHouse, $TorrentTags, $TorrentTagIDs, $TorrentTagUserIDs,
    $TagPositiveVotes, $TagNegativeVotes, $GroupFlags) = array_values($TorrentDetails);

$Title = $GroupName;

$Artists = Artists::get_artist($GroupID);
if ($Artists) {
    $ArtistName = Artists::display_artists($Artists, true);
    $Title = display_str(Artists::display_artists($Artists, false)) . $Title;
}

$Extra = '';

if ($GroupYear > 0) {
    $Extra .= ' ['.$GroupYear.']';
}

if ($GroupVanityHouse) {
    $Extra .= ' [Vanity House]';
}

$Title = "Request an Edit: " . $Title . $Extra;

View::show_header($Title);

?>
<div class="thin">
    <div class="header">
        <h2><?=$Title?></h2>
    </div>
    <div class="box pad">
        <div style="margin-bottom: 10px">
            <p><strong class="important_text">You are requesting an edit for...</strong></p>
            <p class="center"><?=$ArtistName?><a href="torrents.php?id=<?=$GroupID?>"><?=$GroupName?><?=$Extra?></a></p>
        </div>
        <div style="margin-bottom: 10px">
            <p>
            Please detail all information that needs to be edited for the torrent group. Include all relevant links (discogs, musicbrainz, etc.).<br /><br />
            This will not generate a report, but will create a thread in the Editing forum.<br /><br />

            What this form can be used for:
            </p>
            <ul>
                <li>'Original Release' information, such as: year, record label, & catalogue number.</li>
                <li>Group rename/typo correction</li>
                <li>Group merging</li>
                <li>etc...</li>
            </ul>
            <p>Do NOT use this form for individual torrents or artists. For individual torrents, use
                the torrent report feature. For artists, go to their respective
                pages and use that edit request feature.</p>
        </div>
        <div>
            <p><strong class="important_text">Edit Details</strong></p>

            <div class="center">
                <form action="torrents.php" method="POST">
                    <input type="hidden" name="action" value="takeeditrequest" />
                    <input type="hidden" name="groupid" value="<?=$GroupID?>" />
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
