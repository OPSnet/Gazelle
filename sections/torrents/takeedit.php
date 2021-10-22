<?php

use OrpheusNET\Logchecker\Logchecker;

authorize();

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //
//******************************************************************************//

$TypeID = (int)$_POST['type'];
$Type = CATEGORY[$TypeID - 1];
$TorrentID = (int)$_POST['torrentid'];
$Properties = [];
$Properties['Name'] = trim($_POST['title'] ?? '');
$Properties['Format'] = $_POST['format'];
$Properties['Media'] = $_POST['media'] ?? '';
$Properties['Encoding'] = $_POST['bitrate'];
$Properties['TorrentDescription'] = trim($_POST['release_desc']);
$Properties['Scene'] = isset($_POST['scene']) ? '1' : '0';
$Properties['HasLog'] = isset($_POST['flac_log']) ? '1' : '0';
$Properties['HasCue'] = isset($_POST['flac_cue']) ? '1' : '0';
$Properties['Remastered'] = isset($_POST['remaster']) ? '1' : '0';

$Properties['BadTags'] = isset($_POST['bad_tags']);
$Properties['BadFolders'] = isset($_POST['bad_folders']);
$Properties['BadFiles'] = isset($_POST['bad_files']);
$Properties['Lineage'] = isset($_POST['missing_lineage']);
$Properties['CassetteApproved'] = isset($_POST['cassette_approved']);
$Properties['LossymasterApproved'] = isset($_POST['lossymaster_approved']);
$Properties['LossywebApproved'] = isset($_POST['lossyweb_approved']);

if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = trim($_POST['album_desc']);
}
if ($Properties['Remastered']) {
    $Properties['UnknownRelease'] = isset($_POST['unknown']) ? '1' : '0';
    $Properties['RemasterYear'] = (int)$_POST['remaster_year'];
    $Properties['RemasterTitle'] = trim($_POST['remaster_title']);
    $Properties['RemasterRecordLabel'] = trim($_POST['remaster_record_label']);
    $Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number']);
} else {
    $Properties['UnknownRelease'] = 0;
    $Properties['RemasterYear'] = '';
    $Properties['RemasterTitle'] = '';
    $Properties['RemasterRecordLabel'] = '';
    $Properties['RemasterCatalogueNumber'] = '';
}

if ($Viewer->permitted('torrents_freeleech')) {
    $Free = $_POST['freeleechtype'] ?? '0';
    if (!in_array($Free, ['0', '1', '2'])) {
        error(0);
    }
    $Properties['FreeLeech'] = $Free;
    if ($Free === '0') {
        $FreeType = '0';
    } else {
        $FreeType = $_POST['freeleechreason'] ?? '0';
        if (!in_array($FreeType, ['0', '1', '2', '3'])) {
            error(0);
        }
    }
    $Properties['FreeLeechType'] = $FreeType;
}

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

[$UserID, $Remastered, $RemasterYear, $CurFreeLeech] = $DB->row('
    SELECT UserID, Remastered, RemasterYear, FreeTorrent
    FROM torrents
    WHERE ID = ?
    ', $TorrentID
);
if (!$UserID) {
    error(404);
}

if ($Viewer->id() != $UserID && !$Viewer->permitted('torrents_edit')) {
    error(403);
}

if ($Remastered == '1' && !$RemasterYear && !$Viewer->permitted('edit_unknowns')) {
    error(403);
}

if ($Properties['UnknownRelease'] && !($Remastered == '1' && !$RemasterYear) && !$Viewer->permitted('edit_unknowns')) {
    //It's Unknown now, and it wasn't before
    if ($Viewer->id() != $UserID) {
        error(403);
    }
}

$Validate = new Gazelle\Util\Validator;
$Validate->setField('type', '1', 'number', 'Not a valid category.', ['range' => [1, count(CATEGORY)]]);
switch ($Type) {
    case 'Music':
        if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease'] && $Properties['RemasterYear'] < 1982 && $Properties['Media'] == 'CD') {
            error('You have selected a year for an album that predates the medium you say it was created on.');
            header("Location: torrents.php?action=edit&id=$TorrentID");
            exit;
        }
        if ($Properties['RemasterTitle'] == 'Original Release') {
            error('"Original Release" is not a valid remaster title.');
            header("Location: torrents.php?action=edit&id=$TorrentID");
            exit;
        }

        $Validate->setFields([
            ['format', '1', 'inarray', 'Not a valid format.', ['inarray' => FORMAT]],
            ['bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]],
            ['media', '1', 'inarray', 'Not a valid media.', ['inarray' => MEDIA]],
            ['release_desc', '0', 'string', 'Invalid release description.', ['range' => [0, 1000000]]],
            ['remaster_title', '0', 'string', 'Remaster title must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_record_label', '0', 'string', 'Remaster record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_catalogue_number', '0', 'string', 'Remaster catalogue number must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ]);

        if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease']) {
            $Validate->setField('remaster_year', '1', 'number', 'Year of remaster/re-issue must be entered.');
        } else {
            $Validate->setField('remaster_year', '0','number', 'Invalid remaster year.');
        }

        if ($Properties['Encoding'] !== 'Other') {
            $Validate->setField('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]);
        } else {
            // Handle 'other' bitrates
            $Validate->setField('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');
        }
        break;

    case 'Audiobooks':
    case 'Comedy':
        $Validate->setFields([
            ['year', '1', 'number', 'The year of the release must be entered.'],
            ['format', '1', 'inarray', 'Not a valid format.', ['inarray' => FORMAT]],
            ['bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]],
            ['release_desc', '0', 'string', 'The release description has a minimum length of 10 characters.', ['rang' => [10, 1000000]]],
        ]);
        // Handle 'other' bitrates
        if ($Properties['Encoding'] !== 'Other') {
            $Validate->setField('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]);
        } else {
            $Validate->setField('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');
        }
        break;

    default:
        break;
}

$Err = $Validate->validate($_POST) ? false : $Validate->errorMessage();
if (!$Err && $Properties['Remastered'] && !$Properties['RemasterYear']) {
    if ($Viewer->id() !== $UserID && !$Viewer->permitted('edit_unknowns')) {
        $Err = "You may not edit someone else's upload to unknown release.";
    }
}

if ($Err) { // Show the upload form, with the data the user entered
    error($Err);
}

// Strip out Amazon's padding
if (isset($Properties['Image'])) {
    $AmazonReg = '/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i';
    $Matches = [];
    if (preg_match($AmazonReg, $Properties['Image'], $Matches)) {
        $Properties['Image'] = $Matches[1].'.jpg';
    }
    ImageTools::blacklisted($Properties['Image']);
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$DB->prepared_query("
    SELECT GroupID, Media, Format, Encoding, Scene, Description AS TorrentDescription,
        RemasterYear, Remastered, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber
    FROM torrents
    WHERE ID = ?
    ", $TorrentID
);
$current = $DB->next_record(MYSQLI_ASSOC, false);
$change = [];
foreach ($current as $key => $value) {
    if (in_array($key, ['GroupID'])) {
        // Not needed here, used below
        continue;
    }
    if (isset($Properties[$key]) && $value !== $Properties[$key]) {
        $change[] = sprintf('%s %s &rarr; %s', $key, $value, $Properties[$key]);
    }
}

// Some browsers will report an empty file when you submit, prune those out
$_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });

$DB->begin_transaction(); // It's all or nothing

$logfileSummary = new \Gazelle\LogfileSummary;
$logfiles = [];
if (count($_FILES['logfiles']['name']) > 0) {
    ini_set('upload_max_filesize', 1000000);
    $ripFiler = new \Gazelle\File\RipLog;
    $htmlFiler = new \Gazelle\File\RipLogHTML;
    foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
        if (!$_FILES['logfiles']['size'][$Pos]) {
            continue;
        }
        $logfile = new \Gazelle\Logfile(
            $_FILES['logfiles']['tmp_name'][$Pos],
            $_FILES['logfiles']['name'][$Pos]
        );
        $logfiles[] = $logfile;
        $logfileSummary->add($logfile);

        $DB->prepared_query('
            INSERT INTO torrents_logs
                   (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Details)
            VALUES (?,         ?,      ?,         ?,        ?,      ?,              ?,         ?,             ?,                 ?)
            ', $TorrentID, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(), $logfile->ripper(),
                $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
                Logchecker::getLogcheckerVersion(), $logfile->detailsAsString()
        );
        $LogID = $DB->inserted_id();
        $ripFiler->put($logfile->filepath(), [$TorrentID, $LogID]);
        $htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);
    }
}

// Update info for the torrent
$set = [
    'Description = ?', 'Media = ?', 'Format = ?', 'Encoding = ?', 'Scene = ?',
    'Remastered = ?', 'RemasterYear = ?', 'RemasterTitle = ?',
    'RemasterRecordLabel = ?', 'RemasterCatalogueNumber = ?',
];
$args = [
    $Properties['TorrentDescription'], $Properties['Media'], $Properties['Format'], $Properties['Encoding'], $Properties['Scene'],
    $Properties['Remastered'], $Properties['RemasterYear'], $Properties['RemasterTitle'],
    $Properties['RemasterRecordLabel'], $Properties['RemasterCatalogueNumber'],
];

if ($logfiles) {
    [$score, $checksum] = $DB->row("
        SELECT min(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
            min(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
        FROM torrents_logs
        WHERE TorrentID = ?
        GROUP BY TorrentID
        ", $TorrentID
    );
    $set = array_merge($set, ['LogScore = ?', 'LogChecksum = ?', 'HasLogDB = ?']);
    $args = array_merge($args, [$score, $checksum, '1']);
}

if ($Viewer->permitted('torrents_freeleech')) {
    $set = array_merge($set, ['FreeTorrent = ?', 'FreeLeechType = ?']);
    $args = array_merge($args, [$Properties['FreeLeech'], $Properties['FreeLeechType']]);
}

if ($Viewer->permitted('users_mod')) {
    $set = array_merge($set, ['HasLog = ?', 'HasCue = ?']);
    if ($Properties['Format'] == 'FLAC' && $Properties['Media'] == 'CD') {
        $args = array_merge($args, [$Properties['HasLog'], $Properties['HasCue']]);
    } else {
        $args = array_merge($args, ['0', '0']);
    }

    $bfiID = $DB->scalar('SELECT TorrentID FROM torrents_bad_files WHERE TorrentID = ?', $TorrentID);
    if (!$bfiID && $Properties['BadFiles']) {
        $change[] = 'Bad Files checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_files (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($bfiID && !$Properties['BadFiles']) {
        $change[] = 'Bad Files cleared';
        $DB->prepared_query('DELETE FROM torrents_bad_files WHERE TorrentID = ?', $TorrentID);
    }

    $bfID = $DB->scalar('SELECT TorrentID FROM torrents_bad_folders WHERE TorrentID = ?', $TorrentID);
    if (!$bfID && $Properties['BadFolders']) {
        $change[] = 'Bad Folders checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_folders (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($bfID && !$Properties['BadFolders']) {
        $change[] = 'Bad Folders cleared';
        $DB->prepared_query('DELETE FROM torrents_bad_folders WHERE TorrentID = ?', $TorrentID);
    }

    $btID = $DB->scalar('SELECT TorrentID FROM torrents_bad_tags WHERE TorrentID = ?', $TorrentID);
    if (!$btID && $Properties['BadTags']) {
        $change[] = 'Bad Tags checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_tags (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($btID && !$Properties['BadTags']) {
        $change[] = 'Bad Tags cleared';
        $DB->prepared_query('DELETE FROM torrents_bad_tags WHERE TorrentID = ?', $TorrentID);
    }

    $caID = $DB->scalar('SELECT TorrentID FROM torrents_cassette_approved WHERE TorrentID = ?', $TorrentID);
    if (!$caID && $Properties['CassetteApproved']) {
        $change[] = 'Cassette Approved checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_cassette_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($caID && !$Properties['CassetteApproved']) {
        $change[] = 'Cassette Approved cleared';
        $DB->prepared_query('DELETE FROM torrents_cassette_approved WHERE TorrentID = ?', $TorrentID);
    }

    $lmaID = $DB->scalar('SELECT TorrentID FROM torrents_lossymaster_approved WHERE TorrentID = ?', $TorrentID);
    if (!$lmaID && $Properties['LossymasterApproved']) {
        $change[] = 'Lossy Master checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_lossymaster_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($lmaID && !$Properties['LossymasterApproved']) {
        $change[] = 'Lossy Master cleared';
        $DB->prepared_query('DELETE FROM torrents_lossymaster_approved WHERE TorrentID = ?', $TorrentID);
    }

    $lwID = $DB->scalar('SELECT TorrentID FROM torrents_lossyweb_approved WHERE TorrentID = ?', $TorrentID);
    if (!$lwID && $Properties['LossywebApproved']) {
        $change[] = 'Lossy WEB checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_lossyweb_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($lwID && !$Properties['LossywebApproved']) {
        $change[] = 'Lossy WEB cleared';
        $DB->prepared_query('DELETE FROM torrents_lossyweb_approved WHERE TorrentID = ?', $TorrentID);
    }

    $mlID = $DB->scalar('SELECT TorrentID FROM torrents_missing_lineage WHERE TorrentID = ?', $TorrentID);
    if (!$mlID && $Properties['Lineage']) {
        $change[] = 'Missing Lineage checked';
        $DB->prepared_query('INSERT IGNORE INTO torrents_missing_lineage (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $Viewer->id()
        );
    } elseif ($mlID && !$Properties['Lineage']) {
        $change[] = 'Missing Lineage cleared';
        $DB->prepared_query('DELETE FROM torrents_missing_lineage WHERE TorrentID = ?', $TorrentID);
    }
}

$args[] = $TorrentID;
$DB->prepared_query("
    UPDATE torrents SET
    " . implode(', ', $set) . "
    WHERE ID = ?
    ", ...$args
);

$DB->commit();

if ($Viewer->permitted('torrents_freeleech') && $Properties['FreeLeech'] != $CurFreeLeech) {
    Torrents::freeleech_torrents($TorrentID, $Properties['FreeLeech'], $Properties['FreeLeechType']);
}
(new \Gazelle\Manager\TGroup)->refresh($current['GroupID']);

$name = $DB->scalar("
    SELECT g.Name
    FROM torrents_group g
    INNER JOIN torrents t ON (t.GroupID = g.ID)
    WHERE t.ID = ?
    ", $TorrentID
);
$changeLog = implode(', ', $change);
(new Gazelle\Log)->torrent($current['GroupID'], $TorrentID, $Viewer->id(), $changeLog)
    ->general("Torrent $TorrentID ($name) in group {$current['GroupID']} was edited by "
        . $Viewer->username() . " ($changeLog)");

$Cache->deleteMulti(["torrents_details_{$current['GroupID']}", "torrent_download_$TorrentID"]);

header("Location: torrents.php?id={$current['GroupID']}");
