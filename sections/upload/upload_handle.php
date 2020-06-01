<?php

use OrpheusNET\Logchecker\Logchecker;

//******************************************************************************//
//--------------- Take upload --------------------------------------------------//
// This pages handles the backend of the torrent upload function. It checks     //
// the data, and if it all validates, it builds the torrent file, then writes   //
// the data to the database and the torrent to the disk.                        //
//******************************************************************************//

ini_set('max_file_uploads', 100);
define('MAX_FILENAME_LENGTH', 255);

require(__DIR__ . '/../torrents/functions.php');
require(__DIR__ . '/../../classes/file_checker.class.php');

enforce_login();
authorize();

$Validate = new Validate;
$Feed = new Feed;

define('QUERY_EXCEPTION', true); // Shut up debugging

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //

$Err = null;
$Properties = [];
$Type = $Categories[(int)$_POST['type']];
$TypeID = $_POST['type'] + 1;
$Properties['CategoryName'] = $Type;
$Properties['Title'] = trim($_POST['title']);
// Remastered is an Enum in the DB
$Properties['Remastered'] = isset($_POST['remaster']) ? '1' : '0';
if ($Properties['Remastered'] || isset($_POST['unknown'])) {
    $Properties['UnknownRelease'] = isset($_POST['unknown']) ? 1 : 0;
    $Properties['RemasterYear'] = trim($_POST['remaster_year'] ?? '');
    $Properties['RemasterTitle'] = trim($_POST['remaster_title'] ?? '');
    $Properties['RemasterRecordLabel'] = trim($_POST['remaster_record_label'] ?? '');
    $Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number'] ?? '');
}
if (!$Properties['Remastered'] || $Properties['UnknownRelease']) {
    $Properties['UnknownRelease'] = 1;
    $Properties['RemasterYear'] = '';
    $Properties['RemasterTitle'] = '';
    $Properties['RemasterRecordLabel'] = '';
    $Properties['RemasterCatalogueNumber'] = '';
}
$Properties['Year'] = trim($_POST['year']);
$Properties['RecordLabel'] = trim($_POST['record_label'] ?? '');
$Properties['CatalogueNumber'] = trim($_POST['catalogue_number'] ?? '');
$Properties['ReleaseType'] = $_POST['releasetype'];
$Properties['Scene'] = isset($_POST['scene']) ? '1' : '0';
$Properties['Format'] = trim($_POST['format']);
$Properties['Media'] = trim($_POST['media'] ?? '');
$Properties['Encoding'] = $Properties['Bitrate'] = trim($_POST['bitrate'] ?? '');
if ($Properties['Encoding'] == 'Other') {
    $_POST['other_bitrate'] = trim($_POST['other_bitrate'] ?? '');
}
$Properties['MultiDisc'] = $_POST['multi_disc'] ?? null;
$Properties['TagList'] = array_unique(array_map('trim', explode(',', $_POST['tags']))); // Musicbranes loves to send duplicates
$Properties['Image'] = trim($_POST['image'] ?? '');
$Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
$Properties['VanityHouse'] = (int)($_POST['vanity_house'] ?? null && check_perms('torrents_edit_vanityhouse'));
$Properties['TorrentDescription'] = trim($_POST['release_desc'] ?? '');
if ($_POST['album_desc']) {
    $Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
} elseif ($_POST['desc']) {
    $Properties['GroupDescription'] = trim($_POST['desc'] ?? '');
}
$Properties['GroupID'] = $_POST['groupid'] ?? null;
if (empty($_POST['artists'])) {
    $Err = "You didn't enter any artists";
} else {
    $Artists = $_POST['artists'];
    $Importance = $_POST['importance'];
}
if (!empty($_POST['requestid'])) {
    $RequestID = $_POST['requestid'];
    $Properties['RequestID'] = $RequestID;
}
//******************************************************************************//
//--------------- Validate data in upload form ---------------------------------//

$Validate->SetFields('type', '1', 'inarray', 'Please select a valid type.', ['inarray' => array_keys($Categories)]);
switch ($Type) {
    case 'Music':
        if (!$Properties['GroupID']) {
            $Validate->SetFields('title',
                '1','string','Title must be between 1 and 200 characters.', ['maxlength'=>200, 'minlength'=>1]);

            $Validate->SetFields('year',
                '1','number','The year of the original release must be entered.', ['length'=>40]);

            $Validate->SetFields('releasetype',
                '1','inarray','Please select a valid release type.', ['inarray'=>array_keys($ReleaseTypes)]);

            $Validate->SetFields('tags',
                '1','string','You must enter at least one tag. Maximum length is 200 characters.', ['maxlength'=>200, 'minlength'=>2]);

            $Validate->SetFields('record_label',
                '0','string','Record label must be between 2 and 80 characters.', ['maxlength'=>80, 'minlength'=>2]);

            $Validate->SetFields('catalogue_number',
                '0','string','Catalogue Number must be between 2 and 80 characters.', ['maxlength'=>80, 'minlength'=>2]);

            $Validate->SetFields('album_desc',
                '1','string','The album description has a minimum length of 10 characters.', ['maxlength'=>1000000, 'minlength'=>10]);

            if ($Properties['Media'] == 'CD' && !$Properties['Remastered']) {
                $Validate->SetFields('year', '1', 'number', 'You have selected a year for an album that predates the media you say it was created on.', ['minlength'=>1982]);
            }
        }

        if ($Properties['Remastered'] && !$Properties['UnknownRelease']) {
            $Validate->SetFields('remaster_year',
                '1','number','Year of remaster/re-issue must be entered.');
        } else {
            $Validate->SetFields('remaster_year',
                '0','number','Invalid remaster year.');
        }

        if ($Properties['Media'] == 'CD' && $Properties['Remastered']) {
            $Validate->SetFields('remaster_year', '1', 'number', 'You have selected a year for an album that predates the media you say it was created on.', ['minlength'=>1982]);
        }

        $Validate->SetFields('remaster_title',
            '0','string','Remaster title must be between 2 and 80 characters.', ['maxlength'=>80, 'minlength'=>2]);
        if ($Properties['RemasterTitle'] == 'Original Release') {
            $Validate->SetFields('remaster_title', '0', 'string', '"Orginal Release" is not a valid remaster title.');
        }

        $Validate->SetFields('remaster_record_label',
            '0','string','Remaster record label must be between 2 and 80 characters.', ['maxlength'=>80, 'minlength'=>2]);

        $Validate->SetFields('remaster_catalogue_number',
            '0','string','Remaster catalogue number must be between 2 and 80 characters.', ['maxlength'=>80, 'minlength'=>2]);

        $Validate->SetFields('format',
            '1','inarray','Please select a valid format.', ['inarray'=>$Formats]);

        // Handle 'other' bitrates
        if ($Properties['Encoding'] == 'Other') {
            if ($Properties['Format'] == 'FLAC') {
                $Validate->SetFields('bitrate',
                    '1','string','FLAC bitrate must be lossless.', ['regex'=>'/Lossless/']);
            }

            $Validate->SetFields('other_bitrate',
                '1','string','You must enter the other bitrate (max length: 9 characters).', ['maxlength'=>9]);
            $enc = $_POST['other_bitrate'];
            if (isset($_POST['vbr'])) {
                $enc.= ' (VBR)';
            }

            $Properties['Encoding'] = $Properties['Bitrate'] = $enc;
        } else {
            $Validate->SetFields('bitrate',
                '1','inarray','You must choose a bitrate.', ['inarray'=>$Bitrates]);
        }

        $Validate->SetFields('media',
            '1','inarray','Please select a valid media.', ['inarray'=>$Media]);

        $Validate->SetFields('image',
            '0','link','The image URL you entered was invalid.', ['maxlength'=>255, 'minlength'=>12]);

        $Validate->SetFields('release_desc',
            '0','string','The release description you entered is too long.', ['maxlength'=>1000000]);

        $Validate->SetFields('groupid', '0', 'number', 'Group ID was not numeric');

        break;

    case 'Audiobooks':
    case 'Comedy':
        $Validate->SetFields('title',
            '1','string','Title must be between 2 and 200 characters.', ['maxlength'=>200, 'minlength'=>2]);

        $Validate->SetFields('year',
            '1','number','The year of the release must be entered.');

        $Validate->SetFields('format',
            '1','inarray','Please select a valid format.', ['inarray'=>$Formats]);

        if ($Properties['Encoding'] == 'Other') {
            $Validate->SetFields('other_bitrate',
                '1','string','You must enter the other bitrate (max length: 9 characters).', ['maxlength'=>9]);
            $enc = $_POST['other_bitrate'];
            if (isset($_POST['vbr'])) {
                $enc.= ' (VBR)';
            }

            $Properties['Encoding'] = $Properties['Bitrate'] = $enc;
        } else {
            $Validate->SetFields('bitrate',
                '1','inarray','You must choose a bitrate.', ['inarray'=>$Bitrates]);
        }

        $Validate->SetFields('album_desc',
            '1','string','You must enter a proper audiobook description.', ['maxlength'=>1000000, 'minlength'=>10]);

        $Validate->SetFields('tags',
            '1','string','You must enter at least one tag. Maximum length is 200 characters.', ['maxlength'=>200, 'minlength'=>2]);

        $Validate->SetFields('release_desc',
            '0','string','The release description you entered is too long.', ['maxlength'=>1000000]);

        $Validate->SetFields('image',
            '0','link','The image URL you entered was invalid.', ['maxlength'=>255, 'minlength'=>12]);
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $Validate->SetFields('title',
            '1','string','Title must be between 2 and 200 characters.', ['maxlength'=>200, 'minlength'=>2]);

        $Validate->SetFields('tags',
            '1','string','You must enter at least one tag. Maximum length is 200 characters.', ['maxlength'=>200, 'minlength'=>2]);

        $Validate->SetFields('release_desc',
            '0','string','The release description you entered is too long.', ['maxlength'=>1000000]);

        $Validate->SetFields('image',
            '0','link','The image URL you entered was invalid.', ['maxlength'=>255, 'minlength'=>12]);
        break;
}

$Validate->SetFields('rules',
    '1','require','Your torrent must abide by the rules.');

$Err = $Validate->ValidateForm($_POST); // Validate the form

$File = $_FILES['file_input']; // This is our torrent file
$TorrentName = $File['tmp_name'];

if (!is_uploaded_file($TorrentName) || !filesize($TorrentName)) {
    $Err = 'No torrent file uploaded, or file is empty.';
} elseif (substr(strtolower($File['name']), strlen($File['name']) - strlen('.torrent')) !== '.torrent') {
    $Err = "You seem to have put something other than a torrent file into the upload field. (".$File['name'].").";
}

if ($Type == 'Music') {
    //extra torrent files
    $ExtraTorrents = [];
    $DupeNames = [];
    $DupeNames[] = $_FILES['file_input']['name'];

    if (isset($_POST['extra_format']) && isset($_POST['extra_bitrate'])) {
        for ($i = 1; $i <= 5; $i++) {
            if (isset($_FILES["extra_file_$i"])) {
                $ExtraFile = $_FILES["extra_file_$i"];
                $ExtraTorrentName = $ExtraFile['tmp_name'];
                if (!is_uploaded_file($ExtraTorrentName) || !filesize($ExtraTorrentName)) {
                    $Err = 'No extra torrent file uploaded, or file is empty.';
                } elseif (substr(strtolower($ExtraFile['name']), strlen($ExtraFile['name']) - strlen('.torrent')) !== '.torrent') {
                    $Err = 'You seem to have put something other than an extra torrent file into the upload field. (' . $ExtraFile['name'] . ').';
                } elseif (in_array($ExtraFile['name'], $DupeNames)) {
                    $Err = 'One or more torrents has been entered into the form twice.';
                } else {
                    $j = $i - 1;
                    $ExtraTorrents[$ExtraTorrentName]['Name'] = $ExtraTorrentName;
                    $ExtraFormat = trim($_POST['extra_format'][$j]);
                    if (empty($ExtraFormat)) {
                        $Err = 'Missing format for extra torrent.';
                        break;
                    } else {
                        $ExtraTorrents[$ExtraTorrentName]['Format'] = $ExtraFormat;
                    }
                    $ExtraBitrate = trim($_POST['extra_bitrate'][$j]);
                    if (empty($ExtraBitrate)) {
                        $Err = 'Missing bitrate for extra torrent.';
                        break;
                    } else {
                        $ExtraTorrents[$ExtraTorrentName]['Encoding'] = $ExtraBitrate;
                    }
                    $ExtraReleaseDescription = trim($_POST['extra_release_desc'][$j]);
                    $ExtraTorrents[$ExtraTorrentName]['TorrentDescription'] = $ExtraReleaseDescription;
                    $DupeNames[] = $ExtraFile['name'];
                }
            }
        }
    }
}

//Multiple artists
$LogName = '';
if (empty($Properties['GroupID']) && empty($ArtistForm) && $Type == 'Music') {
    $MainArtistCount = 0;
    $ArtistNames = [];
    $ArtistForm = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
        5 => [],
        6 => []
    ];
    for ($i = 0, $il = count($Artists); $i < $il; $i++) {
        $Artists[$i] = trim($Artists[$i]);
        if ($Artists[$i] != '') {
            if (!in_array($Artists[$i], $ArtistNames)) {
                $ArtistForm[$Importance[$i]][] = ['name' => Artists::normalise_artist_name($Artists[$i])];
                if ($Importance[$i] == 1) {
                    $MainArtistCount++;
                }
                $ArtistNames[] = $Artists[$i];
            }
        }
    }
    if ($MainArtistCount < 1) {
        $Err = 'Please enter at least one main artist';
        $ArtistForm = [];
    }
    $LogName .= Artists::display_artists($ArtistForm, false, true, false);
}

if ($Err) { // Show the upload form, with the data the user entered
    $UploadForm = $Type;
    require(__DIR__ . '/upload.php');
    die();
}

if (!empty($Properties['GroupID']) && empty($ArtistForm) && $Type == 'Music') {
    $DB->prepared_query('
        SELECT ta.ArtistID, aa.Name, ta.Importance
        FROM torrents_artists AS ta
        INNER JOIN artists_alias AS aa ON (ta.AliasID = aa.AliasID)
        WHERE ta.GroupID = ?
        ORDER BY ta.Importance ASC, aa.Name ASC
        ', $Properties['GroupID']
    );
    while (list($ArtistID, $ArtistName, $ArtistImportance) = $DB->next_record(MYSQLI_NUM, false)) {
        $ArtistForm[$ArtistImportance][] = ['id' => $ArtistID, 'name' => display_str($ArtistName)];
        $ArtistsUnescaped[$ArtistImportance][] = ['name' => $ArtistName];
    }
    $LogName .= Artists::display_artists($ArtistsUnescaped, false, true, false);
}

if ($Properties['Image']) {
    // Strip out Amazon's padding
    $AmazonReg = '/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i';
    $Matches = [];
    if (preg_match($AmazonReg, $Properties['Image'], $Matches)) {
        $Properties['Image'] = $Matches[1].'.jpg';
    }
    if (!preg_match('/^'.IMAGE_REGEX.'$/i', $Properties['Image'])) {
        $Properties['Image'] = '';
    } else {
        ImageTools::blacklisted($Properties['Image']);
    }
}

//******************************************************************************//
//--------------- Generate torrent file ----------------------------------------//

$torrentFiler = new \Gazelle\File\Torrent;
$Tor = new BencodeTorrent($TorrentName, true);
$PublicTorrent = $Tor->make_private(); // The torrent is now private.
$UnsourcedTorrent = $Tor->set_source(); // The source is now OPS
$InfoHash = pack('H*', $Tor->info_hash());

$ID = $DB->scalar('SELECT ID FROM torrents WHERE info_hash = ?', $InfoHash);
if ($ID) {
    $DB->prepared_query('
        SELECT TorrentID
        FROM torrents_files
        WHERE TorrentID = ?', $ID);
    if ($DB->has_results()) {
        $Err = '<a href="torrents.php?torrentid='.$ID.'">The exact same torrent file already exists on the site!</a>';
    } else {
        // A lost torrent
        $DB->prepared_query('
            INSERT INTO torrents_files (TorrentID, File)
            VALUES (?, ?)
            ', $ID, $Tor->encode()
        );
        $torrentFiler->put($Tor->encode(), $ID);
        $Err = '<a href="torrents.php?torrentid='.$ID.'">Thank you for fixing this torrent</a>';
    }
}

if (isset($Tor->Dec['encrypted_files'])) {
    $Err = 'This torrent contains an encrypted file list which is not supported here.';
}

// File list and size
list($TotalSize, $FileList) = $Tor->file_list();
$HasLog = '0';
$HasCue = '0';
$TmpFileList = [];
$TooLongPaths = [];
$DirName = (isset($Tor->Dec['info']['files']) ? Format::make_utf8($Tor->get_name()) : '');
$IgnoredLogFileNames = ['audiochecker.log', 'sox.log'];
check_name($DirName); // check the folder name against the blacklist
foreach ($FileList as $File) {
    list($Size, $Name) = $File;
    // add +log to encoding
    if ($Properties['Media'] == 'CD' && $Properties['Encoding'] == "Lossless" && !in_array(strtolower($Name), $IgnoredLogFileNames) && preg_match('/\.log$/i', $Name)) {
        $HasLog = '1';
    }
    // add +cue to encoding
    if ($Properties['Encoding'] == "Lossless" && preg_match('/\.cue$/i', $Name)) {
        $HasCue = '1';
    }
    // Check file name and extension against blacklist/whitelist
    check_file($Type, $Name);
    // Make sure the filename is not too long
    if (mb_strlen($Name, 'UTF-8') + mb_strlen($DirName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
        $TooLongPaths[] = "<li>$DirName/$Name</li>";
    }
    // Add file info to array
    $TmpFileList[] = Torrents::filelist_format_file($File);
}
if (count($TooLongPaths) > 0) {
    $Err = 'The torrent contained one or more files with too long a name: <ul>'
        . implode('', $TooLongPaths)
        . '</ul><br />';
}
$Debug->set_flag('upload: torrent decoded');

$logfileSummary = new \Gazelle\LogfileSummary;
if ($HasLog == '1') {
    ini_set('upload_max_filesize', 1000000);
    // Some browsers will report an empty file when you submit, prune those out
    $_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });
    foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
        if (!$_FILES['logfiles']['size'][$Pos]) {
            continue;
        }

        $logfile = new \Gazelle\Logfile(
            $_FILES['logfiles']['tmp_name'][$Pos],
            $_FILES['logfiles']['name'][$Pos]
        );
        $logfileSummary->add($logfile);
    }
}
$LogInDB = count($logfileSummary->all()) ? '1' : '0';

if ($Type == 'Music') {
    $ExtraTorrentsInsert = [];
    foreach ($ExtraTorrents as $ExtraTorrent) {
        $Name = $ExtraTorrent['Name'];
        $ExtraTorrentsInsert[$Name] = $ExtraTorrent;
        $ThisInsert =& $ExtraTorrentsInsert[$Name];
        $ExtraTor = new BencodeTorrent($Name, true);
        if (isset($ExtraTor->Dec['encrypted_files'])) {
            $Err = 'At least one of the torrents contain an encrypted file list which is not supported here';
            break;
        }
        if (!$ExtraTor->is_private()) {
            $ExtraTor->make_private(); // The torrent is now private.
            $PublicTorrent = true;
        }

        if ($ExtraTor->set_source()) {
            $UnsourcedTorrent = true;
        }

        // File list and size
        list($ExtraTotalSize, $ExtraFileList) = $ExtraTor->file_list();
        $ExtraDirName = isset($ExtraTor->Dec['info']['files']) ? Format::make_utf8($ExtraTor->get_name()) : '';

        $ExtraTmpFileList = [];
        foreach ($ExtraFileList as $ExtraFile) {
            list($ExtraSize, $ExtraName) = $ExtraFile;

            check_file($Type, $ExtraName);

            // Make sure the file name is not too long
            if (mb_strlen($ExtraName, 'UTF-8') + mb_strlen($ExtraDirName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
                $Err = "The torrent contained one or more files with too long of a name: <br />$ExtraDirName/$ExtraName";
                break;
            }
            // Add file and size to array
            $ExtraTmpFileList[] = Torrents::filelist_format_file($ExtraFile);
        }

        // To be stored in the database
        $ThisInsert['FilePath'] = $ExtraDirName;
        $ThisInsert['FileString'] = implode("\n", $ExtraTmpFileList);
        $ThisInsert['InfoHash'] = pack('H*', $ExtraTor->info_hash());
        $ThisInsert['NumFiles'] = count($ExtraFileList);
        $ThisInsert['TorEnc'] = $ExtraTor->encode();
        $ThisInsert['TotalSize'] = $ExtraTotalSize;

        $Debug->set_flag('upload: torrent decoded');
        $ExtraID = $DB->scalar('SELECT ID FROM torrents WHERE info_hash = ?', $ThisInsert['InfoHash']);
        if ($ExtraID) {
            $DB->prepared_query('
                SELECT TorrentID
                FROM torrents_files
                WHERE TorrentID = ?', $ExtraID);
            if ($DB->has_results()) {
                $Err = "<a href=\"torrents.php?torrentid=$ExtraID\">The exact same torrent file already exists on the site!</a>";
            } else {
                //One of the lost torrents.
                $DB->prepared_query('
                    INSERT INTO torrents_files (TorrentID, File)
                    VALUES (?, ?)
                    ', $ExtraID, $ThisInsert['TorEnc']
                );
                $torrentFiler->put($ThisInsert['TorEnc'], $ExtraID);
                $Err = "<a href=\"torrents.php?torrentid=$ExtraID\">Thank you for fixing this torrent.</a>";
            }
        }
    }
    unset($ThisInsert);
}

if ($Err) {
    $UploadForm = $Type;
    // TODO: Repopulate the form correctly
    require(__DIR__ . '/upload.php');
    die();
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$NoRevision = false;
if ($Type == 'Music') {
    // Does it belong in a group?
    if ($Properties['GroupID']) {
        $DB->prepared_query('
            SELECT
                ID,
                WikiImage,
                WikiBody,
                RevisionID,
                Name,
                Year,
                ReleaseType,
                TagList
            FROM torrents_group
            WHERE id = ?
            ', $Properties['GroupID']
        );
        if ($DB->has_results()) {
            // Don't escape tg.Name. It's written directly to the log table
            list($GroupID, $WikiImage, $WikiBody, $RevisionID, $Properties['Title'], $Properties['Year'], $Properties['ReleaseType'], $TagList)
                = $DB->next_record(MYSQLI_NUM, [4]);
            $Properties['TagList'] = explode(',', str_replace([' ', '.', '_'], [', ', '.', '.'], $TagList));
            if (!$Properties['Image'] && $WikiImage) {
                $Properties['Image'] = $WikiImage;
            }
            if (strlen($WikiBody) > strlen($Properties['GroupDescription'])) {
                $Properties['GroupDescription'] = $WikiBody;
                if (!$Properties['Image'] || $Properties['Image'] == $WikiImage) {
                    $NoRevision = true;
                }
            }
            $Properties['Artist'] = Artists::display_artists(Artists::get_artist($GroupID), false, false);
        }
    }
    if (!isset($GroupID)) {
        foreach ($ArtistForm as $Importance => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                $DB->prepared_query('
                    SELECT
                        tg.id,
                        tg.WikiImage,
                        tg.WikiBody,
                        tg.RevisionID
                    FROM torrents_group AS tg
                    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
                    LEFT JOIN artists_group AS ag ON (ta.ArtistID = ag.ArtistID)
                    WHERE ag.Name = ?
                        AND tg.Name = ?
                        AND tg.ReleaseType = ?
                        AND tg.Year = ?
                    ', $Artist['name'], $Properties['Title'], $Properties['ReleaseType'], $Properties['Year']
                );

                if ($DB->has_results()) {
                    list($GroupID, $WikiImage, $WikiBody, $RevisionID) = $DB->next_record();
                    if (!$Properties['Image'] && $WikiImage) {
                        $Properties['Image'] = $WikiImage;
                    }
                    if (strlen($WikiBody) > strlen($Properties['GroupDescription'])) {
                        $Properties['GroupDescription'] = $WikiBody;
                        if (!$Properties['Image'] || $Properties['Image'] == $WikiImage) {
                            $NoRevision = true;
                        }
                    }
                    $ArtistForm = Artists::get_artist($GroupID);
                    //This torrent belongs in a group
                    break;

                } else {
                    // The album hasn't been uploaded. Try to get the artist IDs
                    $DB->prepared_query('
                        SELECT
                            ArtistID,
                            AliasID,
                            Name,
                            Redirect
                        FROM artists_alias
                        WHERE Name = ?
                        ', $Artist['name']
                    );
                    if ($DB->has_results()) {
                        while (list($ArtistID, $AliasID, $AliasName, $Redirect) = $DB->next_record(MYSQLI_NUM, false)) {
                            if (!strcasecmp($Artist['name'], $AliasName)) {
                                if ($Redirect) {
                                    $AliasID = $Redirect;
                                }
                                $ArtistForm[$Importance][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $AliasName];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
}

//Needs to be here as it isn't set for add format until now
$LogName .= $Properties['Title'];

//For notifications--take note now whether it's a new group
$IsNewGroup = !isset($GroupID);

//----- Start inserts
if ($IsNewGroup) {
    if ($Type == 'Music') {
        //array to store which artists we have added already, to prevent adding an artist twice
        $ArtistsAdded = [];
        $ArtistManager = new \Gazelle\Manager\Artist;
        foreach ($ArtistForm as $Importance => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                if (!$Artist['id']) {
                    if (isset($ArtistsAdded[strtolower($Artist['name'])])) {
                        $ArtistForm[$Importance][$Num] = $ArtistsAdded[strtolower($Artist['name'])];
                    } else {
                        list($ArtistID, $AliasID) = $ArtistManager->createArtist($Artist['name']);
                        $ArtistForm[$Importance][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $Artist['name']];
                        $ArtistsAdded[strtolower($Artist['name'])] = $ArtistForm[$Importance][$Num];
                    }
                }
            }
        }
        unset($ArtistsAdded);
    }

    // Create torrent group
    $DB->prepared_query('
        INSERT INTO torrents_group
               (CategoryID, Name, Year, RecordLabel, CatalogueNumber, WikiBody, WikiImage, ReleaseType, VanityHouse)
        VALUES (?,          ?,    ?,    ?,           ?,               ?,        ?,         ?,           ?)
        ', $TypeID, $Properties['Title'], $Properties['Year'], $Properties['RecordLabel'], $Properties['CatalogueNumber'],
            $Properties['GroupDescription'], $Properties['Image'], $Properties['ReleaseType'], $Properties['VanityHouse']
    );
    $GroupID = $DB->inserted_id();
    if ($Type == 'Music') {
        foreach ($ArtistForm as $Importance => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                $DB->prepared_query('
                    INSERT IGNORE INTO torrents_artists
                           (GroupID, ArtistID, AliasID, UserID, Importance)
                    VALUES (?,       ?,        ?,       ?,      ?)
                    ', $GroupID, $Artist['id'], $Artist['aliasid'], $LoggedUser['ID'], $Importance
                );
                $Cache->increment('stats_album_count');
            }
        }
    }
    $Cache->increment('stats_group_count');
} else {
    $DB->prepared_query('
        UPDATE torrents_group
        SET Time = now()
        WHERE ID = ?
        ', $GroupID
    );
    $Cache->deleteMulti(["torrent_group_$GroupID", "torrents_details_$GroupID", "detail_files_$GroupID"]);
    if ($Type == 'Music') {
        $Properties['ReleaseType'] = $DB->scalar('
            SELECT ReleaseType
            FROM torrents_group
            WHERE ID = ?
            ', $GroupID
        );
    }
}

// Description
if ($NoRevision) {
    $DB->prepared_query('
        INSERT INTO wiki_torrents
               (PageID, Body, UserID, Image, Summary)
        VALUES (?,      ?,    ?,      ?,     ?)
        ', $GroupID, $Properties['GroupDescription'], $LoggedUser['ID'], $Properties['Image'], 'Uploaded new torrent'
    );
    $RevisionID = $DB->inserted_id();

    // Revision ID
    $DB->prepared_query('
        UPDATE torrents_group
        SET RevisionID = ?
        WHERE ID = ?
        ', $RevisionID, $GroupID
    );
}

// Tags
$tagMan = new \Gazelle\Manager\Tag;
if (!$Properties['GroupID']) {
    foreach ($Properties['TagList'] as $Tag) {
        $Tag = $tagMan->resolve($tagMan->sanitize($Tag));
        if (!empty($Tag)) {
            $TagID = $tagMan->create($Tag, $LoggedUser['ID']);
            $tagMan->createTorrentTag($TagID, $GroupID, $LoggedUser['ID'], 10);
        }
    }
}

// Torrent
$DB->prepared_query("
    INSERT INTO torrents
        (GroupID, UserID, Media, Format, Encoding,
        Remastered, RemasterYear, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber,
        Scene, HasLog, HasCue, HasLogDB, LogScore,
        LogChecksum, info_hash, FileCount, FileList, FilePath,
        Size, Description, Time, FreeTorrent, FreeLeechType)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, now(), '0', '0')
    ", $GroupID, $LoggedUser['ID'], $Properties['Media'], $Properties['Format'], $Properties['Encoding'],
       $Properties['Remastered'], $Properties['RemasterYear'], $Properties['RemasterTitle'], $Properties['RemasterRecordLabel'], $Properties['RemasterCatalogueNumber'],
       $Properties['Scene'], $HasLog, $HasCue, $LogInDB, $logfileSummary->overallScore(),
       $logfileSummary->checksumStatus(), $InfoHash, count($FileList), implode("\n", $TmpFileList), $DirName,
       $TotalSize, $Properties['TorrentDescription']
);

$Cache->increment('stats_torrent_count');
$TorrentID = $DB->inserted_id();

$DB->prepared_query('
    INSERT INTO torrents_leech_stats (TorrentID)
    VALUES (?)
    ', $TorrentID
);

Tracker::update_tracker('add_torrent', ['id' => $TorrentID, 'info_hash' => rawurlencode($InfoHash), 'freetorrent' => 0]);
$Debug->set_flag('upload: ocelot updated');

// Prevent deletion of this torrent until the rest of the upload process is done
// (expire the key after 10 minutes to prevent locking it for too long in case there's a fatal error below)
$Cache->cache_value("torrent_{$TorrentID}_lock", true, 600);

//******************************************************************************//
//--------------- Write Log DB       -------------------------------------------//

$ripFiler = new \Gazelle\File\RipLog;
$htmlFiler = new \Gazelle\File\RipLogHTML;
foreach($logfileSummary->all() as $logfile) {
    $DB->prepared_query('
        INSERT INTO torrents_logs
               (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Log, Details)
        VALUES (?,         ?,      ?,         ?,        ?,      ?,             ?,          ?,             ?,                 ?,   ?)
        ', $TorrentID, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(),
            $logfile->ripper(), $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
            Logchecker::getLogcheckerVersion(), $logfile->text(), $logfile->detailsAsString()
    );
    $LogID = $DB->inserted_id();
    if (!move_uploaded_file($logfile->filepath(), $ripFiler->pathLegacy([$TorrentID, $LogID]))) {
        $Debug->analysis(
            sprintf('failed copy from [%s] to [%s]',
                $logfile->filepath(), $ripFiler->pathLegacy([$TorrentID, $LogID])),
            3600 * 24
        );
    }
    copy($ripFiler->pathLegacy([$TorrentID, $LogID]), $ripFiler->path([$TorrentID, $LogID]));
    $htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);
}

//******************************************************************************//
//--------------- Write torrent file -------------------------------------------//

$DB->prepared_query('
    INSERT INTO torrents_files
           (TorrentID, File)
    VALUES (?,         ?)
    ', $TorrentID, $Tor->encode()
);
$torrentFiler->put($Tor->encode(), $TorrentID);
Misc::write_log("Torrent $TorrentID ($LogName) (".number_format($TotalSize / (1024 * 1024), 2).' MB) was uploaded by ' . $LoggedUser['Username']);
Torrents::write_group_log($GroupID, $TorrentID, $LoggedUser['ID'], 'uploaded ('.number_format($TotalSize / (1024 * 1024), 2).' MB)', 0);

Torrents::update_hash($GroupID);
$Debug->set_flag('upload: sphinx updated');

// Running total for amount of BP to give
$Bonus = new \Gazelle\Bonus;
$BonusPoints = $Bonus->getTorrentValue($Properties['Format'], $Properties['Media'], $Properties['Bitrate'], $LogInDB,
    $logfileSummary->overallScore(), $logfileSummary->checksumStatus());

//******************************************************************************//
//---------------IRC announce and feeds ---------------------------------------//
$Announce = '';

if ($Type == 'Music') {
    $Announce .= Artists::display_artists($ArtistForm, false);
}
$Announce .= $Properties['Title'] . ' ';
$Details = "";
if ($Type == 'Music') {
    $Announce .= '['.$Properties['Year'].']';
    if (($Type == 'Music') && ($Properties['ReleaseType'] > 0)) {
        $Announce .= ' ['.$ReleaseTypes[$Properties['ReleaseType']].']';
    }
    $Details .= $Properties['Format'].' / '.$Properties['Bitrate'];
    if ($HasLog == 1) {
        $Details .= ' / Log'.($LogInDB ? " ({$logfileSummary->overallScore()}%)" : "");
    }
    if ($HasCue == 1) {
        $Details .= ' / Cue';
    }
    $Details .= ' / '.$Properties['Media'];
    if ($Properties['Scene'] == '1') {
        $Details .= ' / Scene';
    }
}

$Title = $Announce;
if ($Details !== "") {
    $Title .= " - ".$Details;
    $Announce .= "\003 - \00310" . $Details . "\003";
}

$AnnounceSSL = "\002TORRENT:\002 \00303{$Announce}\003"
    . " - \00312" . implode(',', $Properties['TagList']) . "\003"
    . " - \00304".site_url()."torrents.php?id=$GroupID\003 / \00304".site_url()."torrents.php?action=download&id=$TorrentID\003";

// ENT_QUOTES is needed to decode single quotes/apostrophes
send_irc('PRIVMSG #ANNOUNCE :'.html_entity_decode($AnnounceSSL, ENT_QUOTES));
$Debug->set_flag('upload: announced on irc');

//******************************************************************************//
//--------------- Upload Extra torrents ----------------------------------------//

foreach ($ExtraTorrentsInsert as $ExtraTorrent) {
    $BonusPoints += $Bonus->getTorrentValue($ExtraTorrent['Format'], $Properties['Media'], $ExtraTorrent['Encoding']);

    $DB->prepared_query("
        INSERT INTO torrents
            (GroupID, UserID, Media, Format, Encoding,
            Remastered, RemasterYear, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber,
            info_hash, FileCount, FileList, FilePath, Size, Description,
            Time, LogScore, HasLog, HasCue, FreeTorrent, FreeLeechType)
        VALUES
            (?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            now(), 0, '0', '0', '0', '0')
        ", $GroupID, $LoggedUser['ID'], $Properties['Media'], $ExtraTorrent['Format'], $ExtraTorrent['Encoding'],
        $Properties['Remastered'], $Properties['RemasterYear'], $Properties['RemasterTitle'], $Properties['RemasterRecordLabel'], $Properties['RemasterCatalogueNumber'],
        $ExtraTorrent['InfoHash'], $ExtraTorrent['NumFiles'], $ExtraTorrent['FileString'],
        $ExtraTorrent['FilePath'], $ExtraTorrent['TotalSize'], $ExtraTorrent['TorrentDescription']
    );

    $Cache->increment('stats_torrent_count');
    $ExtraTorrentID = $DB->inserted_id();

    $DB->prepared_query('
        INSERT INTO torrents_leech_stats (TorrentID)
        VALUES (?)
        ', $ExtraTorrentID
    );

    Tracker::update_tracker('add_torrent', ['id' => $ExtraTorrentID, 'info_hash' => rawurlencode($ExtraTorrent['InfoHash']), 'freetorrent' => 0]);

    //******************************************************************************//
    //--------------- Write torrent file -------------------------------------------//

    $DB->prepared_query('
        INSERT INTO torrents_files
               (TorrentID, File)
        VALUES (?,         ?)
        ', $ExtraTorrentID, $ExtraTorrent['TorEnc']
    );
    $torrentFiler->put($ExtraTorrent['TorEnc'], $ExtraTorrentID);
    $sizeMB = number_format($ExtraTorrent['TotalSize'] / (1024 * 1024), 2);
    Misc::write_log("Torrent $ExtraTorrentID ($LogName) ($sizeMB  MB) was uploaded by " . $LoggedUser['Username']);
    Torrents::write_group_log($GroupID, $ExtraTorrentID, $LoggedUser['ID'], "uploaded ($sizeMB MB)", 0);
    Torrents::update_hash($GroupID);
}

//******************************************************************************//
//--------------- Give Bonus Points  -------------------------------------------//

if (G::$LoggedUser['DisablePoints'] == 0) {
    $Bonus->addPoints($LoggedUser['ID'], $BonusPoints);
}

//******************************************************************************//
//--------------- Recent Uploads (KISS) ----------------------------------------//

if ($Properties['Image'] != '') {
    $Cache->delete_value('user_recent_up_'.$LoggedUser['ID']);
}

//******************************************************************************//
//--------------- Post-processing ----------------------------------------------//
/* Because tracker updates and notifications can be slow, we're
 * redirecting the user to the destination page and flushing the buffers
 * to make it seem like the PHP process is working in the background.
 */

if ($PublicTorrent || $UnsourcedTorrent) {
    View::show_header('Warning');
?>
    <h1>Warning</h1>
    <p><strong>Your torrent has been uploaded; however, you must download your torrent from <a href="torrents.php?id=<?=$GroupID?>">here</a> because:</strong></p>
    <ul>
<?php
    if ($PublicTorrent) {
?>
        <li><strong>You didn't make your torrent using the "private" option</strong></li>
<?php
    }
    if ($UnsourcedTorrent) {
?>
        <li><strong>The "source" flag was not set to OPS. Please read the <a href="/wiki.php?action=article&id=<?= SOURCE_FLAG_WIKI_PAGE_ID ?>">wiki page about source flags</a> to find out why this is important. </strong></li>
<?php
    }
?>
    </ul>
<?php
    View::show_footer();
} elseif ($RequestID) {
    header("Location: requests.php?action=takefill&requestid=$RequestID&torrentid=$TorrentID&auth=".$LoggedUser['AuthKey']);
} else {
    header("Location: torrents.php?id=$GroupID");
}
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    ob_flush();
    flush();
    ob_start(); // So we don't keep sending data to the client
}

// Manage notifications
$UsedFormatBitrates = [];

if (!$IsNewGroup) {
    // maybe there are torrents in the same release as the new torrent. Let's find out (for notifications)
    $GroupInfo = get_group_info($GroupID, 0, false);

    $ThisMedia = display_str($Properties['Media']);
    $ThisRemastered = display_str($Properties['Remastered']);
    $ThisRemasterYear = display_str($Properties['RemasterYear']);
    $ThisRemasterTitle = display_str($Properties['RemasterTitle']);
    $ThisRemasterRecordLabel = display_str($Properties['RemasterRecordLabel']);
    $ThisRemasterCatalogueNumber = display_str($Properties['RemasterCatalogueNumber']);

    foreach ($GroupInfo[1] as $TorrentInfo) {
        if (($TorrentInfo['Media'] == $ThisMedia)
            && ($TorrentInfo['Remastered'] == $ThisRemastered)
            && ($TorrentInfo['RemasterYear'] == (int)$ThisRemasterYear)
            && ($TorrentInfo['RemasterTitle'] == $ThisRemasterTitle)
            && ($TorrentInfo['RemasterRecordLabel'] == $ThisRemasterRecordLabel)
            && ($TorrentInfo['RemasterCatalogueNumber'] == $ThisRemasterCatalogueNumber)
            && ($TorrentInfo['ID'] != $TorrentID)) {
            $UsedFormatBitrates[] = ['format' => $TorrentInfo['Format'], 'bitrate' => $TorrentInfo['Encoding']];
        }
    }
}

// For RSS
$Item = $Feed->item(
    $Title,
    Text::strip_bbcode($Properties['GroupDescription']),
    'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id=' . $TorrentID,
    $LoggedUser['Username'],
    'torrents.php?id=' . $GroupID,
    implode(',', $Properties['TagList'])
);

//Notifications
$SQL = "
    SELECT unf.ID, unf.UserID, torrent_pass
    FROM users_notify_filters AS unf
        JOIN users_main AS um ON um.ID = unf.UserID
    WHERE um.Enabled = '1'";
if (empty($ArtistsUnescaped)) {
    $ArtistsUnescaped = $ArtistForm;
}
if (!empty($ArtistsUnescaped)) {
    $ArtistNameList = [];
    $GuestArtistNameList = [];
    foreach ($ArtistsUnescaped as $Importance => $Artists) {
        foreach ($Artists as $Artist) {
            if ($Importance == 1 || $Importance == 4 || $Importance == 5 || $Importance == 6) {
                $ArtistNameList[] = "Artists LIKE '%|".db_string(str_replace('\\', '\\\\', $Artist['name']), true)."|%'";
            } else {
                $GuestArtistNameList[] = "Artists LIKE '%|".db_string(str_replace('\\', '\\\\', $Artist['name']), true)."|%'";
            }
        }
    }
    // Don't add notification if >2 main artists or if tracked artist isn't a main artist
    if (count($ArtistNameList) > 2 || $Artist['name'] == 'Various Artists') {
        $SQL .= " AND (ExcludeVA = '0' AND (";
        $SQL .= implode(' OR ', array_merge($ArtistNameList, $GuestArtistNameList));
        $SQL .= " OR Artists = ''))";
    } else {
        $SQL .= " AND (";
        if (!empty($GuestArtistNameList)) {
            $SQL .= "(ExcludeVA = '0' AND (";
            $SQL .= implode(' OR ', $GuestArtistNameList);
            $SQL .= ')) OR ';
        }
        $SQL .= implode(' OR ', $ArtistNameList);
        $SQL .= " OR Artists = '')";
    }
} else {
    $SQL .= "AND (Artists = '')";
}

$TagSQL = [];
$NotTagSQL = [];
foreach ($Properties['TagList'] as $Tag) {
    $TagSQL[] = " Tags LIKE '%|".db_string($Tag)."|%' ";
    $NotTagSQL[] = " NotTags LIKE '%|".db_string($Tag)."|%' ";
}

$TagSQL[] = "Tags = ''";

$SQL .= ' AND (' . implode(' OR ', $TagSQL) . ')';
$SQL .= " AND !(" . implode(' OR ', $NotTagSQL) . ')';

$SQL .= " AND (Categories LIKE '%|".db_string($Type)."|%' OR Categories = '') ";

if ($Properties['ReleaseType']) {
    $SQL .= " AND (ReleaseTypes LIKE '%|".db_string($ReleaseTypes[$Properties['ReleaseType']])."|%' OR ReleaseTypes = '') ";
} else {
    $SQL .= " AND (ReleaseTypes = '') ";
}

/*
    Notify based on the following:
        1. The torrent must match the formatbitrate filter on the notification
        2. If they set NewGroupsOnly to 1, it must also be the first torrent in the group to match the formatbitrate filter on the notification
*/


if ($Properties['Format']) {
    $SQL .= " AND (Formats LIKE '%|".db_string($Properties['Format'])."|%' OR Formats = '') ";
} else {
    $SQL .= " AND (Formats = '') ";
}

if ($_POST['bitrate']) {
    $SQL .= " AND (Encodings LIKE '%|".db_string($_POST['bitrate'])."|%' OR Encodings = '') ";
} else {
    $SQL .= " AND (Encodings = '') ";
}

if ($Properties['Media']) {
    $SQL .= " AND (Media LIKE '%|".db_string($Properties['Media'])."|%' OR Media = '') ";
} else {
    $SQL .= " AND (Media = '') ";
}

// Either they aren't using NewGroupsOnly
$SQL .= "AND ((NewGroupsOnly = '0' ";
// Or this is the first torrent in the group to match the formatbitrate filter
$SQL .= ") OR ( NewGroupsOnly = '1' ";
// Test the filter doesn't match any previous formatbitrate in the group
foreach ($UsedFormatBitrates as $UsedFormatBitrate) {
    $FormatReq = "(Formats LIKE '%|".db_string($UsedFormatBitrate['format'])."|%' OR Formats = '') ";
    $BitrateReq = "(Encodings LIKE '%|".db_string($UsedFormatBitrate['bitrate'])."|%' OR Encodings = '') ";
    $SQL .= "AND (NOT($FormatReq AND $BitrateReq)) ";
}

$SQL .= '))';


if ($Properties['Year'] && $Properties['RemasterYear']) {
    $SQL .= " AND (('".db_string($Properties['Year'])."' BETWEEN FromYear AND ToYear)
            OR ('".db_string($Properties['RemasterYear'])."' BETWEEN FromYear AND ToYear)
            OR (FromYear = 0 AND ToYear = 0)) ";
} elseif ($Properties['Year'] || $Properties['RemasterYear']) {
    $SQL .= " AND (('".db_string(max($Properties['Year'],$Properties['RemasterYear']))."' BETWEEN FromYear AND ToYear)
            OR (FromYear = 0 AND ToYear = 0)) ";
} else {
    $SQL .= " AND (FromYear = 0 AND ToYear = 0) ";
}
$SQL .= " AND UserID != '".$LoggedUser['ID']."' ";

$DB->prepared_query('
    SELECT Paranoia
    FROM users_main
    WHERE ID = ?
    ', $LoggedUser['ID']
);
list($Paranoia) = $DB->next_record();
$Paranoia = unserialize($Paranoia);
if (!is_array($Paranoia)) {
    $Paranoia = [];
}
if (!in_array('notifications', $Paranoia)) {
    $SQL .= " AND (Users LIKE '%|".$LoggedUser['ID']."|%' OR Users = '') ";
}

$SQL .= " AND UserID != '".$LoggedUser['ID']."' ";
$DB->query($SQL);
$Debug->set_flag('upload: notification query finished');

if ($DB->has_results()) {
    $UserArray = $DB->to_array('UserID');
    $FilterArray = $DB->to_array('ID');

    $InsertSQL = '
        INSERT IGNORE INTO users_notify_torrents (UserID, GroupID, TorrentID, FilterID)
        VALUES ';
    $Rows = [];
    foreach ($UserArray as $User) {
        list($FilterID, $UserID, $Passkey) = $User;
        $Rows[] = "('$UserID', '$GroupID', '$TorrentID', '$FilterID')";
        $Feed->populate("torrents_notify_$Passkey", $Item);
        $Cache->delete_value("notifications_new_$UserID");
    }
    $InsertSQL .= implode(',', $Rows);
    $DB->query($InsertSQL);
    $Debug->set_flag('upload: notification inserts finished');

    foreach ($FilterArray as $Filter) {
        list($FilterID, $UserID, $Passkey) = $Filter;
        $Feed->populate("torrents_notify_{$FilterID}_$Passkey", $Item);
    }
}

// RSS for bookmarks
$DB->prepared_query('
    SELECT u.ID, u.torrent_pass
    FROM users_main AS u
    INNER JOIN bookmarks_torrents AS b ON (b.UserID = u.ID)
    WHERE b.GroupID = ?
    ', $GroupID
);
while (list($UserID, $Passkey) = $DB->next_record()) {
    $Feed->populate("torrents_bookmarks_t_$Passkey", $Item);
}

$Feed->populate('torrents_all', $Item);
$Debug->set_flag('upload: notifications handled');
if ($Type == 'Music') {
    $Feed->populate('torrents_music', $Item);
    if ($Properties['Media'] == 'Vinyl') {
        $Feed->populate('torrents_vinyl', $Item);
    }
    if ($Properties['Bitrate'] == 'Lossless') {
        $Feed->populate('torrents_lossless', $Item);
    }
    if ($Properties['Bitrate'] == '24bit Lossless') {
        $Feed->populate('torrents_lossless24', $Item);
    }
    if ($Properties['Format'] == 'MP3') {
        $Feed->populate('torrents_mp3', $Item);
    }
    if ($Properties['Format'] == 'FLAC') {
        $Feed->populate('torrents_flac', $Item);
    }
}
if ($Type == 'Applications') {
    $Feed->populate('torrents_apps', $Item);
}
if ($Type == 'E-Books') {
    $Feed->populate('torrents_ebooks', $Item);
}
if ($Type == 'Audiobooks') {
    $Feed->populate('torrents_abooks', $Item);
}
if ($Type == 'E-Learning Videos') {
    $Feed->populate('torrents_evids', $Item);
}
if ($Type == 'Comedy') {
    $Feed->populate('torrents_comedy', $Item);
}
if ($Type == 'Comics') {
    $Feed->populate('torrents_comics', $Item);
}

// Clear cache and allow deletion of this torrent now
$Cache->deleteMulti(["torrents_details_$GroupID", "torrent_{$TorrentID}_lock"]);
