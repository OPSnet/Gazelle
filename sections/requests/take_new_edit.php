<?php

//******************************************************************************//
//----------------- Take request -----------------------------------------------//
authorize();

if ($_POST['action'] !== 'takenew' && $_POST['action'] !== 'takeedit') {
    error(0);
}

$NewRequest = ($_POST['action'] === 'takenew');

if (!$NewRequest) {
    $ReturnEdit = true;
}

if ($NewRequest) {
    if (!check_perms('site_submit_requests') || $LoggedUser['BytesUploaded'] < 250 * 1024 * 1024) {
        error(403);
    }
} else {
    $RequestID = $_POST['requestid'];
    if (!intval($RequestID)) {
        error(0);
    }

    $Request = Requests::get_request($RequestID);
    if ($Request === false) {
        error(404);
    }
    $VoteArray = Requests::get_votes_array($RequestID);
    $VoteCount = count($VoteArray['Voters']);
    $IsFilled = !empty($Request['TorrentID']);
    $CategoryName = $CategoriesV2[$Request['CategoryID'] - 1];
    $CanEdit = ((!$IsFilled && $LoggedUser['ID'] == $Request['UserID'] && $VoteCount < 2) || check_perms('site_moderate_requests'));

    if (!$CanEdit) {
        error(403);
    }
}

// Validate
if (empty($_POST['type'])) {
    error(0);
}

$CategoryName = $_POST['type'];
$CategoryID = (array_search($CategoryName, $CategoriesV2) + 1);

if (empty($CategoryID)) {
    error(0);
}

if (empty($_POST['title'])) {
    $Err = 'You forgot to enter the title!';
} else {
    $Title = trim($_POST['title']);
}

if (empty($_POST['tags'])) {
    $Err = 'You forgot to enter any tags!';
} else {
    $Tags = trim($_POST['tags']);
}

if ($NewRequest) {
    if (empty($_POST['amount'])) {
        $Err = 'You forgot to enter any bounty!';
    } else {
        $Bounty = trim($_POST['amount']);
        if (!intval($Bounty)) {
            $Err = 'Your entered bounty is not a number';
        } elseif ($Bounty < 100 * 1024 * 1024) {
            $Err = 'Minimum bounty is 100 MB.';
        }
        $Bytes = $Bounty; //From MB to B
    }
}

if (empty($_POST['image'])) {
    $Image = null;
} else {
    ImageTools::blacklisted($_POST['image']);
    if (preg_match('/'.IMAGE_REGEX.'/', trim($_POST['image'])) > 0) {
            $Image = trim($_POST['image']);
    } else {
        $Err = display_str($_POST['image']).' does not appear to be a valid link to an image.';
    }
}

if (empty($_POST['description'])) {
    $Err = 'You forgot to enter a description.';
} else {
    $Description = trim($_POST['description']);
}

if (empty($_POST['artists'])) {
    $Err = 'You did not enter any artists.';
} else {
    $Artists = $_POST['artists'];
    $Importance = $_POST['importance'];
}

if (!intval($_POST['releasetype']) || !array_key_exists($_POST['releasetype'], $ReleaseTypes)) {
    $Err = 'Please pick a release type';
}

$ReleaseType = $_POST['releasetype'];

if (empty($_POST['all_formats']) && count($_POST['formats']) !== count($Formats)) {
    $FormatArray = $_POST['formats'];
    if (count($FormatArray) < 1) {
        $Err = 'You must require at least one format';
    }
} else {
    $AllFormats = true;
}

if (empty($_POST['all_bitrates']) && count($_POST['bitrates']) !== count($Bitrates)) {
    $BitrateArray = $_POST['bitrates'];
    if (count($BitrateArray) < 1) {
        $Err = 'You must require at least one bitrate';
    }
} else {
    $AllBitrates = true;
}

if (empty($_POST['all_media']) && count($_POST['media']) !== count($Media)) {
    $MediaArray = $_POST['media'];
    if (count($MediaArray) < 1) {
        $Err = 'You must require at least one medium.';
    }
} else {
    $AllMedia = true;
}

//$Bitrates[1] = FLAC
if (!empty($FormatArray) && in_array(array_search('FLAC', $Formats), $FormatArray)) {
    $NeedLog = empty($_POST['needlog']) ? false : true;
    if ($NeedLog) {
        if ($_POST['minlogscore']) {
            $MinLogScore = intval(trim($_POST['minlogscore']));
        } else {
            $MinLogScore = 0;
        }
        if ($MinLogScore < 0 || $MinLogScore > 100) {
            $Err = 'You have entered a minimum log score that is not between 0 and 100 inclusive.';
        }
    }
    $NeedCue = empty($_POST['needcue']) ? false : true;
    //FLAC was picked, require either Lossless or 24 bit Lossless
    if (!$AllBitrates && !in_array(array_search('Lossless', $Bitrates), $BitrateArray) && !in_array(array_search('24bit Lossless', $Bitrates), $BitrateArray)) {
        $Err = 'You selected FLAC as a format but no possible bitrate to fill it (Lossless or 24bit Lossless)';
    }
    $NeedChecksum = empty($_POST['needcksum']) ? false : true;

    if (($NeedCue || $NeedLog || $NeedChecksum)) {
        if (empty($_POST['all_media']) && !(in_array('0', $MediaArray))) {
            $Err = 'Only CD is allowed as media for FLAC + log/cue requests.';
        }
    }
} else {
    $NeedLog = false;
    $NeedCue = false;
    $NeedChecksum = false;
    $MinLogScore = false;
}

// GroupID
if (!empty($_POST['groupid'])) {
    $GroupID = trim($_POST['groupid']);
    if (preg_match('/^'.TORRENT_GROUP_REGEX.'/i', $GroupID, $Matches)) {
        $GroupID = $Matches[4];
    }
    if (intval($GroupID)) {
        $DB->prepared_query('
            SELECT 1
            FROM torrents_group
            WHERE ID = ?  AND CategoryID = ?',
            $GroupID, 1);
        if (!$DB->has_results()) {
            $Err = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
        }
    } else {
        $Err = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
    }
} elseif ($_POST['groupid'] === '0') {
    $GroupID = 0;
}

//Not required
$EditionInfo = empty($_POST['editioninfo']) ? '' : trim($_POST['editioninfo']);
$CatalogueNumber = empty($_POST['cataloguenumber']) ? '' : trim($_POST['cataloguenumber']);
$RecordLabel = empty($_POST['recordlabel']) ? '' : trim($_POST['recordlabel']);

if (empty($_POST['year'])) {
    $Err = 'You forgot to enter the year!';
} else {
    $Year = trim($_POST['year']);
    if (!intval($Year)) {
        $Err = 'Your entered year is not a number.';
    }
}

//Apply OCLC to all types
$OCLC = empty($_POST['oclc']) ? '' : trim($_POST['oclc']);

//For refilling on error
if ($CategoryName === 'Music') {
    $MainArtistCount = 0;
    $ArtistNames = [];
    $ArtistForm = [
        1 => [],
        2 => [],
        3 => []
    ];
    for ($i = 0, $il = count($Artists); $i < $il; $i++) {
        if (trim($Artists[$i]) !== '') {
            if (!in_array($Artists[$i], $ArtistNames)) {
                $ArtistForm[$Importance[$i]][] = ['name' => trim($Artists[$i])];
                if (in_array($Importance[$i], [1, 4, 5, 6])) {
                    $MainArtistCount++;
                }
                $ArtistNames[] = trim($Artists[$i]);
            }
        }
    }
    if ($MainArtistCount < 1) {
        $Err = 'Please enter at least one main artist, conductor, composer, or DJ.';
    }
    if (!isset($ArtistNames[0])) {
        unset($ArtistForm);
    }
}

if (!empty($Err)) {
    error($Err);
    $Div = $_POST['unit'] === 'mb' ? 1024 * 1024 : 1024 * 1024 * 1024;
    $Bounty /= $Div;
    include(SERVER_ROOT.'/sections/requests/new_edit.php');
    die();
}

//Databasify the input
if (empty($AllBitrates)) {
    foreach ($BitrateArray as $Index => $MasterIndex) {
        if (array_key_exists($Index, $Bitrates)) {
            $BitrateArray[$Index] = $Bitrates[$MasterIndex];
        } else {
            //Hax
            error(0);
        }
    }
    $BitrateList = implode('|', $BitrateArray);
} else {
    $BitrateList = 'Any';
}

if (empty($AllFormats)) {
    foreach ($FormatArray as $Index => $MasterIndex) {
        if (array_key_exists($Index, $Formats)) {
            $FormatArray[$Index] = $Formats[$MasterIndex];
        } else {
            //Hax
            error(0);
        }
    }
    $FormatList = implode('|', $FormatArray);
} else {
    $FormatList = 'Any';
}

if (empty($AllMedia)) {
    foreach ($MediaArray as $Index => $MasterIndex) {
        if (array_key_exists($Index, $Media)) {
            $MediaArray[$Index] = $Media[$MasterIndex];
        } else {
            //Hax
            error(0);
        }
    }
    $MediaList = implode('|', $MediaArray);
} else {
    $MediaList = 'Any';
}

$LogCue = '';
if ($NeedLog) {
    $LogCue .= 'Log';
    if ($MinLogScore > 0) {
        if ($MinLogScore >= 100) {
            $LogCue .= ' (100%)';
        } else {
            $LogCue .= ' (>= '.$MinLogScore.'%)';
        }
    }
}
if ($NeedCue) {
    if ($LogCue !== '') {
        $LogCue .= ' + Cue';
    } else {
        $LogCue = 'Cue';
    }
}

//Query time!
if ($NewRequest) {
    $DB->prepared_query('
        INSERT INTO requests (
            TimeAdded, LastVote, Visible, UserID, CategoryID, Title, Year, Image, Description, RecordLabel,
            CatalogueNumber, ReleaseType, BitrateList, FormatList, MediaList, LogCue, Checksum, GroupID, OCLC)
        VALUES (
            now(), now(), 1, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $LoggedUser['ID'], $CategoryID, $Title, $Year, $Image, $Description, $RecordLabel,
        $CatalogueNumber, $ReleaseType, $BitrateList, $FormatList, $MediaList, $LogCue, $NeedChecksum, $GroupID, $OCLC);

    $RequestID = $DB->inserted_id();

} else {
    $DB->prepared_query('
        UPDATE requests
        SET CategoryID = ?, Title = ?, Year = ?, Image = ?, Description = ?, CatalogueNumber = ?, RecordLabel = ?,
            ReleaseType = ?, BitrateList = ?, FormatList = ?, MediaList = ?, LogCue = ?, Checksum = ?, GroupID = ?, OCLC = ?
        WHERE ID = ?',
        $CategoryID, $Title, $Year, $Image, $Description, $CatalogueNumber, $RecordLabel,
        $ReleaseType, $BitrateList, $FormatList, $MediaList, $LogCue, $NeedChecksum, $GroupID, $OCLC,
        $RequestID);

    // We need to be able to delete artists / tags
    $DB->prepared_query('
        SELECT ArtistID
        FROM requests_artists
        WHERE RequestID = ?', $RequestID);
    $RequestArtists = $DB->to_array();
    foreach ($RequestArtists as $RequestArtist) {
        $Cache->delete_value("artists_requests_$RequestArtist");
    }
    $DB->prepared_query('
        DELETE FROM requests_artists
        WHERE RequestID = ?', $RequestID);
    $Cache->delete_value("request_artists_$RequestID");
}

if ($GroupID) {
    $Cache->delete_value("requests_group_$GroupID");
}

/*
 * Multiple Artists!
 * For the multiple artists system, we have 3 steps:
 * 1. See if each artist given already exists and if it does, grab the ID.
 * 2. For each artist that didn't exist, create an artist.
 * 3. Create a row in the requests_artists table for each artist, based on the ID.
 */

$ArtistManager = new \Gazelle\Manager\Artist;
foreach ($ArtistForm as $Importance => $Artists) {
    foreach ($Artists as $Num => $Artist) {
        //1. See if each artist given already exists and if it does, grab the ID.
        $DB->prepared_query('
            SELECT
                ArtistID,
                AliasID,
                Name,
                Redirect
            FROM artists_alias
            WHERE Name = ?', $Artist['name']);

        while (list($ArtistID, $AliasID, $AliasName, $Redirect) = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($Artist['name'], $AliasName)) {
                if ($Redirect) {
                    $AliasID = $Redirect;
                }
                $ArtistForm[$Importance][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $AliasName];
                break;
            }
        }
        if (!$ArtistID) {
            //2. For each artist that didn't exist, create an artist.
            list($ArtistID, $AliasID) = $ArtistManager->createArtist($Artist['name']);
            $ArtistForm[$Importance][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $Artist['name']];
        }
    }
}

//3. Create a row in the requests_artists table for each artist, based on the ID.
foreach ($ArtistForm as $Importance => $Artists) {
    foreach ($Artists as $Num => $Artist) {
        $DB->prepared_query('
            INSERT IGNORE INTO requests_artists
                (RequestID, ArtistID, AliasID, Importance)
            VALUES
                (?, ?, ?, ?)', $RequestID, $Artist['id'], $Artist['aliasid'], $Importance);
        $Cache->increment('stats_album_count');
        $Cache->delete_value('artists_requests_'.$Artist['id']);
    }
}

//Tags
if (!$NewRequest) {
    $DB->prepared_query('
        DELETE FROM requests_tags
        WHERE RequestID = ?', $RequestID);
}

$tagMan = new \Gazelle\Manager\Tag;
$Tags = array_unique(explode(',', $Tags));
foreach ($Tags as $Index => $Tag) {
    $TagID = $tagMan->create($Tag, $LoggedUser['ID']);
    $tagMan->createRequestTag($TagID, $RequestID);
    $Tags[$Index] = $tagMan->name($TagID); // For announce, may have been aliased
}

if ($NewRequest) {
    //Remove the bounty and create the vote
    $DB->prepared_query('
        INSERT INTO requests_votes
            (RequestID, UserID, Bounty)
        VALUES
            (?, ?, ?)',
        $RequestID, $LoggedUser['ID'], $Bytes * (1 - $RequestTax));

    $DB->prepared_query('
        UPDATE users_leech_stats
        SET Uploaded = (Uploaded - ?)
        WHERE UserID = ?',
        $Bytes, $LoggedUser['ID']);
    $Cache->delete_value('user_stats_'.$LoggedUser['ID']);

    $Announce = "\"$Title\" - ".Artists::display_artists($ArtistForm, false, false).' '.site_url()."requests.php?action=view&id=$RequestID - ".implode(' ', $Tags);
    send_irc("PRIVMSG #requests :{$Announce}");
} else {
    $Cache->delete_value("request_$RequestID");
    $Cache->delete_value("request_artists_$RequestID");
}

Requests::update_sphinx_requests($RequestID);

header("Location: requests.php?action=view&id=$RequestID");
