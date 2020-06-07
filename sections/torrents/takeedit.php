<?php

//******************************************************************************//
//--------------- Take edit ----------------------------------------------------//
// This pages handles the backend of the 'edit torrent' function. It checks     //
// the data, and if it all validates, it edits the values in the database       //
// that correspond to the torrent in question.                                  //
//******************************************************************************//

use OrpheusNET\Logchecker\Logchecker;

enforce_login();
authorize();

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //
//******************************************************************************//

$Properties=[];
$TypeID = (int)$_POST['type'];
$Type = $Categories[$TypeID-1];
$TorrentID = (int)$_POST['torrentid'];
$Properties['Remastered'] = (isset($_POST['remaster']))? 1 : 0;
if ($Properties['Remastered']) {
    $Properties['UnknownRelease'] = (isset($_POST['unknown'])) ? 1 : 0;
    $Properties['RemasterYear'] = trim($_POST['remaster_year']);
    $Properties['RemasterTitle'] = $_POST['remaster_title'];
    $Properties['RemasterRecordLabel'] = $_POST['remaster_record_label'];
    $Properties['RemasterCatalogueNumber'] = $_POST['remaster_catalogue_number'];
}
if (!$Properties['Remastered']) {
    $Properties['UnknownRelease'] = 0;
    $Properties['RemasterYear'] = '';
    $Properties['RemasterTitle'] = '';
    $Properties['RemasterRecordLabel'] = '';
    $Properties['RemasterCatalogueNumber'] = '';
}
$Properties['Scene'] = (isset($_POST['scene']))? 1 : 0;
$Properties['HasLog'] = (isset($_POST['flac_log']))? 1 : 0;
$Properties['HasCue'] = (isset($_POST['flac_cue']))? 1 : 0;
$Properties['BadTags'] = (isset($_POST['bad_tags']))? 1 : 0;
$Properties['BadFolders'] = (isset($_POST['bad_folders']))? 1 : 0;
$Properties['BadFiles'] = (isset($_POST['bad_files'])) ? 1 : 0;
$Properties['Lineage'] = (isset($_POST['missing_lineage'])) ? 1 : 0;
$Properties['CassetteApproved'] = (isset($_POST['cassette_approved']))? 1 : 0;
$Properties['LossymasterApproved'] = (isset($_POST['lossymaster_approved']))? 1 : 0;
$Properties['LossywebApproved'] = (isset($_POST['lossyweb_approved'])) ? 1 : 0;
$Properties['Format'] = $_POST['format'];
$Properties['Media'] = $_POST['media'];
$Properties['Bitrate'] = $Properties['Encoding'] = $_POST['bitrate'];
$Properties['TorrentDescription'] = $_POST['release_desc'];
$Properties['Name'] = $_POST['title'] ?? '';
if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = $_POST['album_desc'];
}
if (check_perms('torrents_freeleech')) {
    $Free = $_POST['freeleechtype'];
    if (!in_array($Free, ['0', '1', '2'])) {
        error(0);
    }
    $Properties['FreeLeech'] = $Free;

    if ($Free == '0') {
        $FreeType = '0';
    } else {
        $FreeType = $_POST['freeleechreason'];
        if (!in_array($FreeType, ['0', '1', '2', '3'])) {
            error(0);
        }
    }
    $Properties['FreeLeechType'] = $FreeType;
}

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

list($UserID, $Remastered, $RemasterYear, $CurFreeLeech) = $DB->row('
    SELECT UserID, Remastered, RemasterYear, FreeTorrent
    FROM torrents
    WHERE ID = ?
    ', $TorrentID
);
if (!$UserID) {
    error(404);
}

if ($LoggedUser['ID'] != $UserID && !check_perms('torrents_edit')) {
    error(403);
}

if ($Remastered == '1' && !$RemasterYear && !check_perms('edit_unknowns')) {
    error(403);
}

if ($Properties['UnknownRelease'] && !($Remastered == '1' && !$RemasterYear) && !check_perms('edit_unknowns')) {
    //It's Unknown now, and it wasn't before
    if ($LoggedUser['ID'] != $UserID) {
        //Hax
        die();
    }
}

$Validate = new Validate;
$Validate->SetFields('type', '1', 'number', 'Not a valid type.', ['maxlength' => count($Categories), 'minlength' => 1]);
switch ($Type) {
    case 'Music':
        if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease']) {
            $Validate->SetFields('remaster_year', '1', 'number', 'Year of remaster/re-issue must be entered.');
        } else {
            $Validate->SetFields('remaster_year', '0','number', 'Invalid remaster year.');
        }

        if (!empty($Properties['Remastered']) && !$Properties['UnknownRelease'] && $Properties['RemasterYear'] < 1982 && $Properties['Media'] == 'CD') {
            error('You have selected a year for an album that predates the medium you say it was created on.');
            header("Location: torrents.php?action=edit&id=$TorrentID");
            die();
        }

        $Validate->SetFields('remaster_title', '0', 'string', 'Remaster title must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);

        if ($Properties['RemasterTitle'] == 'Original Release') {
            error('"Original Release" is not a valid remaster title.');
            header("Location: torrents.php?action=edit&id=$TorrentID");
            die();
        }

        $Validate->SetFields('remaster_record_label', '0', 'string', 'Remaster record label must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);

        $Validate->SetFields('remaster_catalogue_number', '0', 'string', 'Remaster catalogue number must be between 2 and 80 characters.', ['maxlength' => 80, 'minlength' => 2]);

        $Validate->SetFields('format', '1', 'inarray', 'Not a valid format.', ['inarray' => $Formats]);

        $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);

        // Handle 'other' bitrates
        if ($Properties['Encoding'] == 'Other') {
            $Validate->SetFields('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $enc = trim($_POST['other_bitrate']);
            if (isset($_POST['vbr'])) {
                $enc .= ' (VBR)';
            }

            $Properties['Encoding'] = $enc;
            $Properties['Bitrate'] = $enc;
        } else {
            $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);
        }

        $Validate->SetFields('media', '1', 'inarray', 'Not a valid media.', ['inarray' => $Media]);

        $Validate->SetFields('release_desc', '0', 'string', 'Invalid release description.', ['maxlength' => 1000000, 'minlength' => 0]);

        break;

    case 'Audiobooks':
    case 'Comedy':
        /*$Validate->SetFields('title', '1', 'string', 'Title must be between 2 and 300 characters.', array('maxlength' => 300, 'minlength' => 2));
        ^ this is commented out because there is no title field on these pages*/
        $Validate->SetFields('year', '1', 'number', 'The year of the release must be entered.');

        $Validate->SetFields('format', '1', 'inarray', 'Not a valid format.', ['inarray' => $Formats]);

        $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);

        // Handle 'other' bitrates
        if ($Properties['Encoding'] == 'Other') {
            $Validate->SetFields('other_bitrate', '1', 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $enc = trim($_POST['other_bitrate']);
            if (isset($_POST['vbr'])) {
                $enc .= ' (VBR)';
            }

            $Properties['Encoding'] = $enc;
            $Properties['Bitrate'] = $enc;
        } else {
            $Validate->SetFields('bitrate', '1', 'inarray', 'You must choose a bitrate.', ['inarray' => $Bitrates]);
        }

        $Validate->SetFields('release_desc', '0', 'string', 'The release description has a minimum length of 10 characters.', ['maxlength' => 1000000, 'minlength' => 10]);

        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        /*$Validate->SetFields('title', '1', 'string', 'Title must be between 2 and 300 characters.', array('maxlength' => 300, 'minlength' => 2));
            ^ this is commented out because there is no title field on these pages*/
        break;
}

$Err = $Validate->ValidateForm($_POST); // Validate the form

if ($Properties['Remastered'] && !$Properties['RemasterYear']) {
    //Unknown Edit!
    if ($LoggedUser['ID'] == $UserID || check_perms('edit_unknowns')) {
        //Fine!
    } else {
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
//--------------- Make variables ready for database input ----------------------//

// Shorten and escape $Properties for database input
$T = [];
foreach ($Properties as $Key => $Value) {
    $T[$Key] = "'".db_string(trim($Value))."'";
    if (!$T[$Key]) {
        $T[$Key] = null;
    }
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$DBTorVals = [];
$DB->prepared_query("
    SELECT Media, Format, Encoding, RemasterYear, Remastered, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber, Scene, Description
    FROM torrents
    WHERE ID = ?", $TorrentID
);
$DBTorVals = $DB->to_array(false, MYSQLI_ASSOC);
$DBTorVals = $DBTorVals[0];
$LogDetails = '';
foreach ($DBTorVals as $Key => $Value) {
    $Value = "'$Value'";
    if (isset($T[$Key]) && $Value != $T[$Key]) {
        if ((empty($Value) && empty($T[$Key])) || ($Value == "'0'" && $T[$Key] == "''")) {
            continue;
        }
        if ($LogDetails == '') {
            $LogDetails = "$Key: $Value -> ".$T[$Key];
        } else {
            $LogDetails = "$LogDetails, $Key: $Value -> ".$T[$Key];
        }
    }
}

// Some browsers will report an empty file when you submit, prune those out
$_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });

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
                   (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Log, Details)
            VALUES (?,         ?,      ?,         ?,        ?,      ?,              ?,         ?,             ?,                 ?,   ?)
            ', $TorrentID, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(), $logfile->ripper(),
                $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
                Logchecker::getLogcheckerVersion(), $logfile->text(), $logfile->detailsAsString()
        );
        $LogID = $DB->inserted_id();
        $ripFiler->put($logfile->filepath(), [$TorrentID, $LogID]);
        $htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);
    }
}

// Update info for the torrent
$SQL = "UPDATE torrents AS t";

if ($logfiles) {
    $SQL .= "
    LEFT JOIN (
      SELECT
          TorrentID,
          MIN(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
          MIN(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
        FROM torrents_logs
        WHERE TorrentID = {$TorrentID}
        GROUP BY TorrentID
       ) AS tl ON t.ID = tl.TorrentID
";
}
$SQL .= "
    SET
        Media = {$T['Media']},
        Format = {$T['Format']},
        Encoding = {$T['Encoding']},
        RemasterYear = {$T['RemasterYear']},
        Remastered = {$T['Remastered']},
        RemasterTitle = {$T['RemasterTitle']},
        RemasterRecordLabel = {$T['RemasterRecordLabel']},
        RemasterCatalogueNumber = {$T['RemasterCatalogueNumber']},
        Scene = {$T['Scene']},";
if ($logfiles) {
    $SQL .= "
        LogScore = CASE WHEN tl.Score IS NULL THEN 100 ELSE tl.Score END,
        LogChecksum = CASE WHEN tl.Checksum IS NULL THEN '1' ELSE tl.Checksum END,
        HasLogDB = '1',";
}

if (check_perms('torrents_freeleech')) {
    $SQL .= "FreeTorrent = {$T['FreeLeech']},";
    $SQL .= "FreeLeechType = {$T['FreeLeechType']},";
}

if (check_perms('users_mod')) {
    if ($T['Format'] == "'FLAC'" && $T['Media'] == "'CD'") {
        $SQL .= "
            HasLog = {$T['HasLog']},
            HasCue = {$T['HasCue']},";
    } else {
        $SQL .= "
            HasLog = '0',
            HasCue = '0',";
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_bad_tags WHERE TorrentID = ?', $TorrentID);
    list($btID) = $DB->fetch_record();

    if (!$btID && $Properties['BadTags']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_tags (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($btID && !$Properties['BadTags']) {
        $DB->prepared_query('DELETE FROM torrents_bad_tags WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_bad_folders WHERE TorrentID = ?', $TorrentID);
    list($bfID) = $DB->fetch_record();

    if (!$bfID && $Properties['BadFolders']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_folders (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($bfID && !$Properties['BadFolders']) {
        $DB->prepared_query('DELETE FROM torrents_bad_folders WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_bad_files WHERE TorrentID = ?', $TorrentID);
    list($bfiID) = $DB->fetch_record();

    if (!$bfiID && $Properties['BadFiles']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_bad_files (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($bfiID && !$Properties['BadFiles']) {
        $DB->prepared_query('DELETE FROM torrents_bad_files WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_missing_lineage WHERE TorrentID = ?', $TorrentID);
    list($mlID) = $DB->fetch_record();

    if (!$mlID && $Properties['Lineage']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_missing_lineage (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($mlID && !$Properties['Lineage']) {
        $DB->prepared_query('DELETE FROM torrents_missing_lineage WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_cassette_approved WHERE TorrentID = ?', $TorrentID);
    list($caID) = $DB->fetch_record();

    if (!$caID && $Properties['CassetteApproved']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_cassette_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($caID && !$Properties['CassetteApproved']) {
        $DB->prepared_query('DELETE FROM torrents_cassette_approved WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_lossymaster_approved WHERE TorrentID = ?', $TorrentID);
    list($lmaID) = $DB->fetch_record();

    if (!$lmaID && $Properties['LossymasterApproved']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_lossymaster_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($lmaID && !$Properties['LossymasterApproved']) {
        $DB->prepared_query('DELETE FROM torrents_lossymaster_approved WHERE TorrentID = ?', $TorrentID);
    }

    $DB->prepared_query('SELECT TorrentID FROM torrents_lossyweb_approved WHERE TorrentID = ?', $TorrentID);
    list($lwID) = $DB->fetch_record();

    if (!$lwID && $Properties['LossywebApproved']) {
        $DB->prepared_query('INSERT IGNORE INTO torrents_lossyweb_approved (TorrentID, UserID) VALUES (?, ?)',
            $TorrentID, $LoggedUser['ID']
        );
    }
    if ($lwID && !$Properties['LossywebApproved']) {
        $DB->prepared_query('DELETE FROM torrents_lossyweb_approved WHERE TorrentID = ?', $TorrentID);
    }
}

$SQL .= "
        Description = {$T['TorrentDescription']}
    WHERE ID = $TorrentID";
$DB->query($SQL);

if (check_perms('torrents_freeleech') && $Properties['FreeLeech'] != $CurFreeLeech) {
    Torrents::freeleech_torrents($TorrentID, $Properties['FreeLeech'], $Properties['FreeLeechType']);
}

$DB->prepared_query('
    SELECT g.ID, g.Name, t.Time
    FROM torrents_group g
    INNER JOIN torrents t ON (t.GroupID = g.ID)
    WHERE t.ID = ?', $TorrentID
);
list($GroupID, $Name, $Time) = $DB->fetch_record();

Misc::write_log("Torrent $TorrentID ($Name) in group $GroupID was edited by ".$LoggedUser['Username']." ($LogDetails)"); // TODO: this is probably broken
Torrents::write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], $LogDetails, 0);
$Cache->delete_value("torrents_details_$GroupID");
$Cache->delete_value("torrent_download_$TorrentID");

Torrents::update_hash($GroupID);
// All done!

header("Location: torrents.php?id=$GroupID");
