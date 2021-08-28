<?php

function print_or_return($JsonMsg, $Error = null) {
    if (defined('NO_AJAX_ERROR')) {
        return $JsonMsg;
    } else {
        json_or_error($JsonMsg, $Error);
    }
}
//******************************************************************************//
//--------------- Fill a request -----------------------------------------------//

/**
 * TODO: this can probably disappear when this page is investigated in greater detail.
 *
 * Search for $Needle in the string $Haystack which is a list of values separated by $Separator.
 * @param string $Haystack
 * @param string $Needle
 * @param string $Separator
 * @param boolean $Strict
 * @return boolean
 */
function search_joined_string($Haystack, $Needle, $Separator = '|', $Strict = true) {
    return (array_search($Needle, explode($Separator, $Haystack), $Strict) !== false);
}

$RequestID = (int)$_REQUEST['requestid'];
if (!$RequestID) {
    error(0);
}

if (!defined('AJAX')) {
    authorize();
}

//VALIDATION
$Err = [];
if (!empty($_GET['torrentid'])) {
    $TorrentID = (int)$_GET['torrentid'];
} else {
    if (empty($_REQUEST['link'])) {
        $Err[] = print_or_return('You forgot to supply a link to the filling torrent');
    } else {
        if (!preg_match(TORRENT_REGEXP, $_REQUEST['link'], $match)) {
            $Err[] = print_or_return('Your link does not appear to be valid (use the [PL] button to obtain the correct URL).');
        } else {
            $TorrentID = (int)$match['id'];
        }
    }
}
if (!$TorrentID) {
    $Err[] = print_or_return('could not determine torrentid', 404);
}

//Torrent exists, check it's applicable
$DB->prepared_query("
    SELECT
        t.GroupID,
        t.UserID,
        t.Time,
        tg.ReleaseType,
        t.Encoding,
        t.Format,
        t.Media,
        t.HasLog,
        t.HasCue,
        t.HasLogDB,
        t.LogScore,
        t.LogChecksum,
        tg.CategoryID,
        IF(t.Remastered = '1', t.RemasterCatalogueNumber, tg.CatalogueNumber),
        CASE WHEN t.Time + INTERVAL 1 HOUR > now() THEN 1 ELSE 0 END as GracePeriod
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (t.GroupID = tg.ID)
    WHERE t.ID = ?", $TorrentID);

if (!$DB->has_results()) {
    $Err[] = print_or_return('invalid torrentid', 404);
}
list($GroupID, $UploaderID, $UploadTime, $TorrentReleaseType, $Bitrate, $Format, $Media, $HasLog, $HasCue, $HasLogDB, $LogScore, $LogChecksum, $TorrentCategoryID, $TorrentCatalogueNumber, $GracePeriod) = $DB->next_record();

$FillerID = $Viewer->id();
$FillerUsername = $Viewer->username();

if (!empty($_REQUEST['user']) && check_perms('site_moderate_requests')) {
    $filler = (new Gazelle\Manager\User)->findByUsername(trim($_REQUEST['user']));
    if (!$filler) {
        $Err[] = 'No such user to fill for!';
    } else {
        $FillerID       = $filler->id();
        $FillerUsername = $filler->username();
    }
}

if ($GracePeriod && $UploaderID !== $FillerID && !check_perms('site_moderate_requests')) {
    $Err[] = "There is a one hour grace period for new uploads to allow the uploader ($FillerUsername) to fill the request.";
}

$DB->prepared_query('
    SELECT
        Title,
        UserID,
        TorrentID,
        CategoryID,
        ReleaseType,
        CatalogueNumber,
        BitrateList,
        FormatList,
        MediaList,
        LogCue,
        Checksum
    FROM requests
    WHERE ID = ?', $RequestID);
list($Title, $RequesterID, $OldTorrentID, $RequestCategoryID, $RequestReleaseType, $RequestCatalogueNumber, $BitrateList, $FormatList, $MediaList, $LogCue, $Checksum)
    = $DB->next_record();

if (!empty($OldTorrentID)) {
    $Err[] = 'This request has already been filled.';
}
if ($RequestCategoryID !== '0' && $TorrentCategoryID !== $RequestCategoryID) {
    $Err[] = 'This torrent is of a different category than the request. If the request is actually miscategorized, please contact staff.';
}

$CategoryName = CATEGORY[$RequestCategoryID - 1];

if ($Format === 'FLAC' && $LogCue && $Media === 'CD') {
    if (strpos($LogCue, 'Log') !== false) {
        if (!$HasLogDB) {
            $Err[] = 'This request requires a log.';
        } else {
            if (preg_match('/(\d+)%/', $LogCue, $Matches) && $LogScore < $Matches[1]) {
                $Err[] = 'This torrent\'s log score is too low.';
            }

            if ($Checksum && !$LogChecksum) {
                $Err[] = 'The ripping log for this torrent does not have a valid checksum.';
            }
        }
    }

    if (strpos($LogCue, 'Cue') !== false && !$HasCue) {
        $Err[] = 'This request requires a cue file.';
    }
}

if ($BitrateList === 'Other') {
    if (in_array($Bitrate, ['24bit Lossless', 'Lossless', 'V0 (VBR)', 'V1 (VBR)', 'V2 (VBR)', 'APS (VBR)', 'APX (VBR)', '256', '320'])) {
        $Err[] = "$Bitrate is not an allowed bitrate for this request.";
    }
} elseif ($BitrateList && $BitrateList != 'Any' && !search_joined_string($BitrateList, $Bitrate)) {
    $Err[] = "$Bitrate is not an allowed bitrate for this request.";
}
if ($FormatList && $FormatList != 'Any' && !search_joined_string($FormatList, $Format)) {
    $Err[] = "$Format is not an allowed format for this request.";
}
if ($MediaList && $MediaList != 'Any' && !search_joined_string($MediaList, $Media)) {
    $Err[] = "$Media is not a permitted media for this request.";
}

if (count($Err)) {
    echo print_or_return($Err, implode('<br />', $Err));
}

//We're all good! Fill!
$DB->prepared_query("
    UPDATE requests SET
        TimeFilled = now(),
        FillerID = ?,
        TorrentID = ?
    WHERE ID = ?
    ", $FillerID, $TorrentID, $RequestID
);
$ArtistForm = Requests::get_artists($RequestID);
$ArtistName = Artists::display_artists($ArtistForm, false, true);
$FullName = $ArtistName.$Title;

$userMan = new Gazelle\Manager\User;
$DB->prepared_query("
    SELECT UserID FROM requests_votes WHERE RequestID = ?
    ", $RequestID
);
$UserIDs = $DB->to_array();
foreach ($UserIDs as $User) {
    [$VoterID] = $User;
    $userMan->sendPM($VoterID, 0,
        "The request \"$FullName\" has been filled",
        "One of your requests&#8202;&mdash;&#8202;[url=requests.php?action=view&amp;id={$RequestID}]$FullName"
            . "[/url]&#8202;&mdash;&#8202;has been filled. You can view it here: [pl]{$TorrentID}[/pl]"
    );
}

$RequestVotes = Requests::get_votes_array($RequestID);
(new Gazelle\Log)->general("Request $RequestID ($FullName) was filled by user $FillerID ($FillerUsername) with the torrent $TorrentID for a "
    . Format::get_size($RequestVotes['TotalBounty']) . ' bounty.'
);

// Give bounty
$DB->prepared_query('
    UPDATE users_leech_stats
    SET Uploaded = Uploaded + ?
    WHERE UserID = ?', $RequestVotes['TotalBounty'], $FillerID);

$Cache->delete_value("user_stats_$FillerID");
$Cache->delete_value("request_$RequestID");
$Cache->delete_value("requests_group_$GroupID");

$DB->prepared_query('
    SELECT ArtistID
    FROM requests_artists
    WHERE RequestID = ?', $RequestID);
$ArtistIDs = $DB->to_array();
foreach ($ArtistIDs as $ArtistID) {
    $Cache->delete_value("artists_requests_$ArtistID");
}

Requests::update_sphinx_requests($RequestID);
$SphQL = new SphinxqlQuery();
$SphQL->raw_query("UPDATE requests, requests_delta SET torrentid = $TorrentID, fillerid = $FillerID WHERE id = $RequestID", false);

if (defined('AJAX')) {
    $data = [
        'requestId' => $RequestID,
        'torrentId' => $TorrentID,
        'fillerId' => $FillerID,
        'fillerName' => $FillerUsername,
        'bounty' => $RequestVotes['TotalBounty'],
    ];
    if ($_GET['action'] === 'request_fill') {
        json_print('success', $data);
    } else {
        return $data;
    }
} else {
    header("Location: requests.php?action=view&id=$RequestID");
}
