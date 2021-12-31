<?php

use Gazelle\Util\Irc;

authorize();

if (empty($_POST['type'])) {
    error(0);
}
$CategoryName = $_POST['type'];
$CategoryID = array_search($CategoryName, CATEGORY);
if ($CategoryID === false) {
    error(0);
}
$CategoryID += 1;

if (!in_array($_POST['action'], ['takenew', 'takeedit'])) {
    error(0);
}
$NewRequest = ($_POST['action'] === 'takenew');

$onlyMetadata = false;
if ($NewRequest) {
    if (!$Viewer->permitted('site_submit_requests') || $Viewer->uploadedSize() < 250 * 1024 * 1024) {
        error(403);
    }
} else {
    $RequestID = (int)$_POST['requestid'];
    if (!$RequestID) {
        error(404);
    }
    $Request = Requests::get_request($RequestID);
    if ($Request === false) {
        error(404);
    }
    $VoteArray = Requests::get_votes_array($RequestID);
    $VoteCount = count($VoteArray['Voters']);
    $IsFilled = !empty($Request['TorrentID']);

    $CanEdit = (!$IsFilled && $Viewer->id() == $Request['UserID'] && $VoteCount < 2)
        || $Viewer->permittedAny('site_edit_requests', 'site_moderate_requests');
    if (!$CanEdit) {
        error(403);
    }
    $onlyMetadata = $Viewer->id() != $Request['UserID'] && $Viewer->permitted('site_edit_requests');
}

if ($NewRequest) {
    if (empty($_POST['amount'])) {
        $Err = 'You forgot to enter any bounty!';
    } else {
        $Bounty = trim($_POST['amount']);
        if (!intval($Bounty)) {
            $Err = 'Your entered bounty is not a number';
        } elseif ($Bounty < 100 * 1024 * 1024) {
            $Err = 'Minimum bounty is 100 MiB.';
        }
        $Bytes = $Bounty; //From MiB to B
    }
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

if (empty($_POST['image'])) {
    $Image = null;
} else {
    $Image = $_POST['image'];
    if (!preg_match(IMAGE_REGEXP, $Image)) {
        $Err = display_str($Image) . " does not look like a valid image url";
    }
    $banned = (new Gazelle\Util\ImageProxy)->badHost($Image);
    if ($banned) {
        $Err = "Please rehost images from $banned elsewhere.";
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
    $role = $_POST['importance'];
}

if (empty($_POST['year'])) {
    $Err = 'You forgot to enter the year!';
} else {
    $Year = trim($_POST['year']);
    if (!intval($Year)) {
        $Err = 'Your entered year is not a number.';
    }
}

// optional
$EditionInfo = empty($_POST['editioninfo']) ? '' : trim($_POST['editioninfo']);
$CatalogueNumber = empty($_POST['cataloguenumber']) ? '' : trim($_POST['cataloguenumber']);
$RecordLabel = empty($_POST['recordlabel']) ? '' : trim($_POST['recordlabel']);

//Apply OCLC to all types
$OCLC = empty($_POST['oclc']) ? '' : trim($_POST['oclc']);

$AllBitrates = false;
$AllFormats  = false;
$$AllMedia   = false;

if (!$onlyMetadata) {
    if (!intval($_POST['releasetype']) || !(new Gazelle\ReleaseType)->findNameById($_POST['releasetype'])) {
        $Err = 'Please pick a release type';
    }
    $ReleaseType = (int)$_POST['releasetype'];

    if (empty($_POST['all_formats']) && count($_POST['formats']) !== count(FORMAT)) {
        $FormatArray = $_POST['formats'];
        if (empty($FormatArray)) {
            $Err = 'You must require at least one format';
        }
    } else {
        $AllFormats = true;
    }

    if (empty($_POST['all_bitrates']) && count($_POST['bitrates']) !== count(ENCODING)) {
        $BitrateArray = $_POST['bitrates'];
        if (empty($BitrateArray)) {
            $Err = 'You must require at least one bitrate';
        }
    } else {
        $AllBitrates = true;
    }

    if (empty($_POST['all_media']) && count($_POST['media']) !== count(MEDIA)) {
        $MediaArray = $_POST['media'];
        if (count($MediaArray) < 1) {
            $Err = 'You must require at least one medium.';
        }
    } else {
        $AllMedia = true;
    }

    if (!empty($FormatArray) && in_array(array_search('FLAC', FORMAT), $FormatArray)) {
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
        if (!$AllBitrates && !in_array(array_search('Lossless', ENCODING), $BitrateArray) && !in_array(array_search('24bit Lossless', ENCODING), $BitrateArray)) {
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
}


// GroupID
if (!empty($_POST['groupid'])) {
    $GroupID = preg_match(TGROUP_REGEXP, trim($_POST['groupid']), $match)
        ? (int)$match['id']
        : (int)$_POST['groupid'];
    if ($GroupID > 0) {
        $tgroup = (new Gazelle\Manager\TGroup)->findById($GroupID);
        if (is_null($tgroup)) {
            $Err = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
        } else {
            $GroupID = $tgroup->id();
        }
    }
}

//For refilling on error
if ($CategoryName === 'Music') {
    $MainArtistCount = 0;
    $ArtistNames = [];
    $ArtistForm = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
        5 => [],
        6 => [],
        7 => [],
        8 => [],
    ];
    for ($i = 0, $il = count($Artists); $i < $il; $i++) {
        if (trim($Artists[$i]) !== '') {
            if (!in_array($Artists[$i], $ArtistNames)) {
                $ArtistForm[$role[$i]][] = ['name' => trim($Artists[$i])];
                if (in_array($role[$i], [1, 4, 5, 6, 8])) {
                    $MainArtistCount++;
                }
                $ArtistNames[] = trim($Artists[$i]);
            }
        }
    }
    if ($MainArtistCount < 1) {
        $Err = 'Please enter at least one main artist, conductor, arranger, composer, or DJ.';
    }
    if (!isset($ArtistNames[0])) {
        unset($ArtistForm);
    }
}

if (!empty($Err)) {
    error($Err);
    $Div = $_POST['unit'] === 'mb' ? 1024 * 1024 : 1024 * 1024 * 1024;
    $Bounty /= $Div;
    require_once('new_edit.php');
    exit;
}

if (!$onlyMetadata) {
    if ($AllBitrates) {
        $BitrateList = 'Any';
    } else {
        foreach ($BitrateArray as $Index => $MasterIndex) {
            if (array_key_exists($Index, ENCODING)) {
                $BitrateArray[$Index] = ENCODING[$MasterIndex];
            } else {
                error(0);
            }
        }
        $BitrateList = implode('|', $BitrateArray);
    }

    if ($AllFormats) {
        $FormatList = 'Any';
    } else {
        foreach ($FormatArray as $Index => $MasterIndex) {
            if (array_key_exists($Index, FORMAT)) {
                $FormatArray[$Index] = FORMAT[$MasterIndex];
            } else {
                error(0);
            }
        }
        $FormatList = implode('|', $FormatArray);
    }

    if ($AllMedia) {
        $MediaList = 'Any';
    } else {
        foreach ($MediaArray as $Index => $MasterIndex) {
            if (array_key_exists($Index, MEDIA)) {
                $MediaArray[$Index] = MEDIA[$MasterIndex];
            } else {
                error(0);
            }
        }
        $MediaList = implode('|', $MediaArray);
    }
}

if (!$NeedLog) {
    $LogCue = '';
} else {
    $LogCue = 'Log';
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

if ($NewRequest) {
    $DB->prepared_query('
        INSERT INTO requests (
            TimeAdded, LastVote, Visible, UserID, CategoryID, Title, Year, Image, Description, RecordLabel,
            CatalogueNumber, ReleaseType, BitrateList, FormatList, MediaList, LogCue, Checksum, GroupID, OCLC)
        VALUES (
            now(), now(), 1, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $Viewer->id(), $CategoryID, $Title, $Year, $Image, $Description, $RecordLabel,
        $CatalogueNumber, $ReleaseType, $BitrateList, $FormatList, $MediaList, $LogCue, $NeedChecksum, $GroupID, $OCLC);
    $RequestID = $DB->inserted_id();

} else {
    if ($onlyMetadata) {
        $DB->prepared_query("
            UPDATE requests SET
                CategoryID = ?, Title = ?, Year = ?, Image = ?, Description = ?, CatalogueNumber = ?, RecordLabel = ?, GroupID = ?, OCLC = ?
            WHERE ID = ?
            ", $CategoryID, $Title, $Year, $Image, $Description, $CatalogueNumber, $RecordLabel, $GroupID, $OCLC,
            $RequestID
        );
    } else {
        $DB->prepared_query('
            UPDATE requests SET
                CategoryID = ?, Title = ?, Year = ?, Image = ?, Description = ?, CatalogueNumber = ?, RecordLabel = ?,
                ReleaseType = ?, BitrateList = ?, FormatList = ?, MediaList = ?, LogCue = ?, Checksum = ?, GroupID = ?, OCLC = ?
            WHERE ID = ?',
            $CategoryID, $Title, $Year, $Image, $Description, $CatalogueNumber, $RecordLabel,
            $ReleaseType, $BitrateList, $FormatList, $MediaList, $LogCue, $NeedChecksum, $GroupID, $OCLC,
            $RequestID
        );
    }

    // We need to be able to delete artists / tags
    $DB->prepared_query("
        SELECT ArtistID FROM requests_artists WHERE RequestID = ?
        ", $RequestID
    );
    $RequestArtists = $DB->to_array();
    foreach ($RequestArtists as $RequestArtist) {
        $Cache->delete_value("artists_requests_$RequestArtist");
    }
    $DB->prepared_query("
        DELETE FROM requests_artists WHERE RequestID = ?
        ", $RequestID
    );
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

$artistMan = new Gazelle\Manager\Artist;
foreach ($ArtistForm as $role => $Artists) {
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

        while ([$ArtistID, $AliasID, $AliasName, $Redirect] = $DB->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($Artist['name'], $AliasName)) {
                if ($Redirect) {
                    $AliasID = $Redirect;
                }
                $ArtistForm[$role][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $AliasName];
                break;
            }
        }
        if (!$ArtistID) {
            //2. For each artist that didn't exist, create an artist.
            [$ArtistID, $AliasID] = $artistMan->create($Artist['name']);
            $ArtistForm[$role][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $Artist['name']];
        }
    }
}

//3. Create a row in the requests_artists table for each artist, based on the ID.
$artistMan->setGroupID($RequestID);
foreach ($ArtistForm as $role => $Artists) {
    foreach ($Artists as $Num => $Artist) {
        $artistMan->addToRequest($Artist['id'], $Artist['aliasid'], $role);
        $Cache->increment('stats_album_count');
        $Cache->delete_value('artists_requests_'.$Artist['id']);
    }
}

//Tags
if (!$NewRequest) {
    $DB->prepared_query("
        DELETE FROM requests_tags WHERE RequestID = ?
        ", $RequestID
    );
}

$tagMan = new Gazelle\Manager\Tag;
$Tags = array_unique(explode(',', $Tags));
foreach ($Tags as $Index => $Tag) {
    $TagID = $tagMan->create($Tag, $Viewer->id());
    $tagMan->createRequestTag($TagID, $RequestID);
    $Tags[$Index] = $tagMan->name($TagID); // For announce, may have been aliased
}

if ($NewRequest) {
    //Remove the bounty and create the vote
    $DB->prepared_query("
        INSERT INTO requests_votes
               (RequestID, UserID, Bounty)
        VALUES (?,         ?,      ?)
        ", $RequestID, $Viewer->id(), $Bytes * (1 - REQUEST_TAX)
    );

    $DB->prepared_query('
        UPDATE users_leech_stats
        SET Uploaded = (Uploaded - ?)
        WHERE UserID = ?',
        $Bytes, $Viewer->id());
    $Cache->delete_value('user_stats_'.$Viewer->id());

    if ($Viewer->option('AutoSubscribe')) {
        (new Gazelle\Subscription($Viewer))->subscribeComments('requests', $RequestID);
    }

    $Announce = "\"$Title\" - ".Artists::display_artists($ArtistForm, false, false).' '.SITE_URL."/requests.php?action=view&id=$RequestID - ".implode(' ', $Tags);
    Irc::sendRaw("PRIVMSG #requests :{$Announce}");
} else {
    $Cache->delete_value("request_$RequestID");
    $Cache->delete_value("request_artists_$RequestID");
}

Requests::update_sphinx_requests($RequestID);

header("Location: requests.php?action=view&id=$RequestID");
