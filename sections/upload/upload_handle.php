<?php

use Gazelle\Util\Irc;
use OrpheusNET\Logchecker\Logchecker;
use OrpheusNET\BencodeTorrent\BencodeTorrent;

//******************************************************************************//
//--------------- Take upload --------------------------------------------------//
// This pages handles the backend of the torrent upload function. It checks     //
// the data, and if it all validates, it builds the torrent file, then writes   //
// the data to the database and the torrent to the disk.                        //
//******************************************************************************//

ini_set('max_file_uploads', 100);
define('MAX_FILENAME_LENGTH', 255);
define('QUERY_EXCEPTION', true); // Shut up debugging

enforce_login();

if (!defined('AJAX')) {
    authorize();
}

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
$Properties['Remastered'] = !empty($_POST['remaster']) ? '1' : '0';
if ($Properties['Remastered'] || !empty($_POST['unknown'])) {
    $Properties['UnknownRelease'] = !empty($_POST['unknown']) ? 1 : 0;
    $Properties['RemasterYear'] = trim($_POST['remaster_year'] ?? '');
    $_POST['remaster_year'] = $Properties['RemasterYear'];
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
$_POST['year'] = $Properties['Year'];
$Properties['RecordLabel'] = trim($_POST['record_label'] ?? '');
$Properties['CatalogueNumber'] = trim($_POST['catalogue_number'] ?? '');
$Properties['ReleaseType'] = $_POST['releasetype'];
$Properties['Scene'] = !empty($_POST['scene']) ? '1' : '0';
$Properties['Format'] = trim($_POST['format']);
$Properties['Media'] = trim($_POST['media'] ?? '');
$Properties['Encoding'] = trim($_POST['bitrate'] ?? '');
if ($Properties['Encoding'] === 'Other') {
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

$isMusicUpload = ($Type === 'Music');

// common to all types
$Validate = new Gazelle\Util\Validator;
$Validate->setFields([
    ['type', '1', 'inarray', 'Please select a valid type.', ['inarray' => array_keys($Categories)]],
    ['release_desc', '0','string','The release description you entered is too long.', ['maxlength'=>1000000]],
    ['rules', '1','require','Your torrent must abide by the rules.'],
]);

if (!$isMusicUpload || ($isMusicUpload && !$Properties['GroupID'])) {
    $Validate->setFields([
        ['image', '0','link','The image URL you entered was invalid.', ['range' => [255, 12]]],
        ['tags', '1','string','You must enter at least one tag. Maximum length is 200 characters.', ['range' => [2, 200]]],
        ['title', '1','string','Title must be less than 200 characters.', ['maxlength' => 200]],
    ]);
}

if ($_POST['album_desc']) {
    $Validate->setField('album_desc', '1','string','The album description has a minimum length of 10 characters.', ['range' => [10, 1000000]]);
} elseif ($_POST['desc']) {
    $Validate->setField('desc', '1','string','The description has a minimum length of 10 characters.', ['range' => [10, 1000000]]);
}

// audio types
if (in_array($Type, ['Music', 'Audiobooks', 'Comedy'])) {
    $Validate->setField('format', '1','inarray','Please select a valid format.', ['inarray'=>$Formats]);
    if ($Properties['Encoding'] !== 'Other') {
        $Validate->setField('bitrate', '1','inarray','You must choose a bitrate.', ['inarray'=>$Bitrates]);
    } else {
        if ($Properties['Format'] === 'FLAC') {
            $Validate->setField('bitrate', '1','string','FLAC bitrate must be lossless.', ['regex'=>'/Lossless/']);
        } else {
            $Validate->setField('other_bitrate',
                '1','string','You must enter the other bitrate (max length: 9 characters).', ['maxlength'=>9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');;
        }
    }
}

$feedType = ['torrents_all'];

$releaseTypes = (new Gazelle\ReleaseType)->list();
switch ($Type) {
    case 'Music':
        $Validate->setFields([
            ['groupid', '0', 'number', 'Group ID was not numeric'],
            ['media', '1','inarray','Please select a valid media.', ['inarray'=>$Media]],
            ['remaster_title', '0','string','Remaster title must be between 2 and 80 characters.', ['range' => [2, 80]]],
            ['remaster_record_label', '0','string','Remaster record label must be between 2 and 80 characters.', ['range' => [2, 80]]],
            ['remaster_catalogue_number', '0','string','Remaster catalogue number must be between 2 and 80 characters.', ['range' => [2, 80]]],
        ]);
        if (!$Properties['GroupID']) {
            $Validate->setFields([
                ['year', '1','number','The year of the original release must be entered.', ['length'=>40]],
                ['releasetype', '1','inarray','Please select a valid release type.', ['inarray'=>array_keys($releaseTypes)]],
                ['record_label', '0','string','Record label must be between 2 and 80 characters.', ['range' => [2, 80]]],
                ['catalogue_number', '0','string','Catalogue Number must be between 2 and 80 characters.', ['range' => [2, 80]]],
            ]);
            if ($Properties['Media'] == 'CD' && !$Properties['Remastered']) {
                $Validate->setField('year', '1', 'number', 'You have selected a year for an album that predates the media you say it was created on.', ['minlength'=>1982]);
            }
        }

        if ($Properties['RemasterTitle'] === 'Original Release') {
            $Validate->setField('remaster_title', '0', 'string', '"Orginal Release" is not a valid remaster title.');
        }
        if (!$Properties['Remastered']) {
            $Validate->setField('remaster_year', '0','number','Invalid remaster year.');
        } else {
            if (!$Properties['UnknownRelease']) {
                $Validate->setField('remaster_year', '1','number','Year of remaster/re-issue must be entered.');
            }
            if ($Properties['Media'] == 'CD' ) {
                $Validate->setField('remaster_year', '1', 'number', 'You have selected a year for an album that predates the media you say it was created on.',
                    ['minlength' => 1982]
                );
            }
        }
        $feedType[] = 'torrents_music';
        if ($Properties['Media'] === 'Vinyl') {
            $feedType[] = 'torrents_vinyl';
        }
        if ($Properties['Encoding'] === 'Lossless') {
            $feedType[] = 'torrents_lossless';
        } elseif ($Properties['Encoding'] === '24bit Lossless') {
            $feedType[] = 'torrents_lossless24';
        }
        if ($Properties['Format'] === 'MP3') {
            $feedType[] = 'torrents_mp3';
        } elseif ($Properties['Format'] === 'FLAC') {
            $feedType[] = 'torrents_flac';
        }
        break;

    case 'Applications':
        $feedType[] = 'torrents_apps';
        break;
    case 'Audiobooks':
        $Validate->setField('year', '1','number','The year of the release must be entered.');
        $feedType[] = 'torrents_abooks';
        break;
    case 'Comedy':
        $feedType[] = 'torrents_comedy';
        break;
    case 'Comics':
        $feedType[] = 'torrents_comics';
        break;
    case 'E-Books':
        $feedType[] = 'torrents_ebooks';
        break;
    case 'E-Learning Videos':
        $feedType[] = '';
        break;
}

$Err = $Validate->validate($_POST) ? false : $Validate->errorMessage();

$File = $_FILES['file_input']; // This is our torrent file
$TorrentName = $File['tmp_name'];
$LogName = '';

if (!is_uploaded_file($TorrentName) || !filesize($TorrentName)) {
    $Err = 'No torrent file uploaded, or file is empty.';
} elseif (substr(strtolower($File['name']), strlen($File['name']) - strlen('.torrent')) !== '.torrent') {
    $Err = "You seem to have put something other than a torrent file into the upload field. (".$File['name'].").";
}

if (!$Err && $isMusicUpload) {
    // additional torrent files
    $ExtraTorrents = [];
    $DupeNames = [$_FILES['file_input']['name']];
    if (!empty($_POST['extra_format']) && !empty($_POST['extra_bitrate'])) {
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($_FILES["extra_file_$i"])) {
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
    unset($DupeNames);

    // Multiple artists
    if (empty($Properties['GroupID'])) {
        $mainArtists = 0;
        $ArtistForm = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
        ];
        for ($i = 0, $end = count($Artists); $i < $end; $i++) {
            $name = Gazelle\Artist::sanitize($Artists[$i]);
            $role = (int)$Importance[$i];
            if ($name === '') {
                continue;
            }
            if (!in_array($name, array_column($ArtistForm[$role], 'name'))) {
                $ArtistForm[$role][] = ['name' => $name];
                if ($role === 1) {
                    $mainArtists++;
                }
            }
        }
        if ($mainArtists < 1) {
            $Err = 'Please enter at least one main artist';
            $ArtistForm = [];
        }
        $LogName .= Artists::display_artists($ArtistForm, false, true, false);
    }
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
    foreach (IMAGE_HOST_BANNED as $banned) {
        if (stripos($banned, $Properties['Image']) !== false) {
            $Err = "Please rehost images from $banned elsewhere.";
            break;
        }
    }
}

if ($Err) { // Show the upload form, with the data the user entered
    if (defined('AJAX')) {
        json_error($Err);
    } else {
        $UploadForm = $Type;
        require(__DIR__ . '/upload.php');
        die();
    }
}

if (!empty($Properties['GroupID']) && empty($ArtistForm) && $isMusicUpload) {
    $DB->prepared_query('
        SELECT ta.ArtistID, aa.Name, ta.Importance
        FROM torrents_artists AS ta
        INNER JOIN artists_alias AS aa ON (ta.AliasID = aa.AliasID)
        WHERE ta.GroupID = ?
        ORDER BY ta.Importance ASC, aa.Name ASC
        ', $Properties['GroupID']
    );
    while ([$ArtistID, $ArtistName, $ArtistImportance] = $DB->next_record(MYSQLI_NUM, false)) {
        $ArtistForm[$ArtistImportance][] = ['id' => $ArtistID, 'name' => display_str($ArtistName)];
        $ArtistsUnescaped[$ArtistImportance][] = ['name' => $ArtistName];
    }
    $LogName .= Artists::display_artists($ArtistsUnescaped, false, true, false);
}

//******************************************************************************//
//--------------- Generate torrent file ----------------------------------------//

$torrentFiler = new Gazelle\File\Torrent();
$Tor = new BencodeTorrent();
$Tor->decodeFile($TorrentName);
$PublicTorrent = $Tor->makePrivate(); // The torrent is now private.
$UnsourcedTorrent = set_source($Tor, SOURCE, GRANDFATHER_SOURCE, GRANDFATHER_OLD_SOURCE, GRANDFATHER_NO_SOURCE);
$InfoHash = $Tor->getHexInfoHash();
$TorData = $Tor->getData();

$ID = $DB->scalar('SELECT ID FROM torrents WHERE info_hash = ?', $InfoHash);
if ($ID) {
    if ($torrentFiler->exists($ID)) {
        $Err = '<a href="torrents.php?torrentid='.$ID.'">The exact same torrent file already exists on the site!</a>';
    } else {
        // A lost torrent
        $torrentFiler->put($Tor->getEncode(), $ID);
        $Err = '<a href="torrents.php?torrentid='.$ID.'">Thank you for fixing this torrent</a>';
    }
}

if (isset($TorData['encrypted_files'])) {
    $Err = 'This torrent contains an encrypted file list which is not supported here.';
}

$checker = new Gazelle\Util\FileChecker;

// File list and size
['total_size' => $TotalSize, 'files' => $FileList] = $Tor->getFileList();
$HasLog = '0';
$HasCue = '0';
$TmpFileList = [];
$TooLongPaths = [];
$DirName = (isset($TorData['info']['files']) ? make_utf8($Tor->getName()) : '');
$IgnoredLogFileNames = ['audiochecker.log', 'sox.log'];

if (!$Err) {
    $Err = $checker->checkName($DirName); // check the folder name against the blacklist
}
foreach ($FileList as $FileInfo) {
    ['path' => $Name, 'size' => $Size] = $FileInfo;
    // add +log to encoding
    if ($Properties['Media'] == 'CD' && $Properties['Encoding'] == "Lossless" && !in_array(strtolower($Name), $IgnoredLogFileNames) && preg_match('/\.log$/i', $Name)) {
        $HasLog = '1';
    }
    // add +cue to encoding
    if ($Properties['Encoding'] == "Lossless" && preg_match('/\.cue$/i', $Name)) {
        $HasCue = '1';
    }
    // Check file name and extension against blacklist/whitelist
    if (!$Err) {
        $Err = $checker->checkFile($Type, $Name);
    }
    // Make sure the filename is not too long
    if (mb_strlen($Name, 'UTF-8') + mb_strlen($DirName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
        $TooLongPaths[] = "<li>$DirName/$Name</li>";
    }
    // Add file info to array
    $TmpFileList[] = Torrents::filelist_format_file($Name, $Size);
}
if (count($TooLongPaths) > 0) {
    $Err = 'The torrent contained one or more files with too long a name: <ul>'
        . implode('', $TooLongPaths)
        . '</ul><br />';
}
$Debug->set_flag('upload: torrent decoded');

$logfileSummary = new Gazelle\LogfileSummary;
if ($HasLog == '1') {
    ini_set('upload_max_filesize', 1000000);
    // Some browsers will report an empty file when you submit, prune those out
    $_FILES['logfiles']['name'] = array_filter($_FILES['logfiles']['name'], function($Name) { return !empty($Name); });
    foreach ($_FILES['logfiles']['name'] as $Pos => $File) {
        if (!$_FILES['logfiles']['size'][$Pos]) {
            continue;
        }

        $logfile = new Gazelle\Logfile(
            $_FILES['logfiles']['tmp_name'][$Pos],
            $_FILES['logfiles']['name'][$Pos]
        );
        $logfileSummary->add($logfile);
    }
}
$LogInDB = count($logfileSummary->all()) ? '1' : '0';

$ExtraTorrentsInsert = [];
// disable extra torrents when using ajax, just have them re-submit multiple times
if ($isMusicUpload) {
    foreach ($ExtraTorrents as $ExtraTorrent) {
        $Name = $ExtraTorrent['Name'];
        $ExtraTorrentsInsert[$Name] = $ExtraTorrent;
        $ThisInsert =& $ExtraTorrentsInsert[$Name];
        $ExtraTor = new BencodeTorrent();
        $ExtraTor->decodeFile($Name);
        $ExtraTorData = $ExtraTor->getData();
        if (isset($ExtraTorData['encrypted_files'])) {
            $Err = 'At least one of the torrents contain an encrypted file list which is not supported here';
            break;
        }
        if (!$ExtraTor->isPrivate()) {
            $ExtraTor->makePrivate(); // The torrent is now private.
            $PublicTorrent = true;
        }

        if (set_source($ExtraTor, SOURCE, GRANDFATHER_SOURCE, GRANDFATHER_OLD_SOURCE, GRANDFATHER_NO_SOURCE)) {
            $UnsourcedTorrent = true;
        }

        // File list and size
        ['total_size' => $ExtraTotalSize, 'files' => $ExtraFileList] = $ExtraTor->getFileList();
        $ExtraDirName = isset($ExtraTorData['info']['files']) ? make_utf8($ExtraTor->getName()) : '';

        $ExtraTmpFileList = [];
        foreach ($ExtraFileList as $ExtraFile) {
            ['path' => $ExtraName, 'size' => $ExtraSize] = $ExtraFile;

            if (!$Err) {
                $Err = $checker->checkFile($Type, $ExtraName);
            }

            // Make sure the file name is not too long
            if (mb_strlen($ExtraName, 'UTF-8') + mb_strlen($ExtraDirName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
                $Err = "The torrent contained one or more files with too long of a name: <br />$ExtraDirName/$ExtraName";
                break;
            }
            // Add file and size to array
            $ExtraTmpFileList[] = Torrents::filelist_format_file($ExtraName, $ExtraSize);
        }

        // To be stored in the database
        $ThisInsert['FilePath'] = $ExtraDirName;
        $ThisInsert['FileString'] = implode("\n", $ExtraTmpFileList);
        $ThisInsert['InfoHash'] = $ExtraTor->getHexInfoHash();
        $ThisInsert['NumFiles'] = count($ExtraFileList);
        $ThisInsert['TorEnc'] = $ExtraTor->getEncode();
        $ThisInsert['TotalSize'] = $ExtraTotalSize;

        $Debug->set_flag('upload: torrent decoded');
        $ExtraID = $DB->scalar('SELECT ID FROM torrents WHERE info_hash = ?', $ThisInsert['InfoHash']);
        if ($ExtraID) {
            if ($torrentFiler->exists($ExtraID)) {
                $Err = "<a href=\"torrents.php?torrentid=$ExtraID\">The exact same torrent file already exists on the site!</a>";
            } else {
                $torrentFiler->put($ThisInsert['TorEnc'], $ExtraID);
                $Err = "<a href=\"torrents.php?torrentid=$ExtraID\">Thank you for fixing this torrent.</a>";
            }
        }
    }
    unset($ThisInsert);
}

if ($Err) {
    if (defined('AJAX')) {
        json_error($Err);
    } else {
        $UploadForm = $Type;
        // TODO: Repopulate the form correctly
        require(__DIR__ . '/upload.php');
        die();
    }
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$NoRevision = false;
if ($isMusicUpload) {
    // Does it belong in a group?
    if ($Properties['GroupID']) {
        $DB->prepared_query("
            SELECT tg.ID,
                tg.WikiImage,
                tg.WikiBody,
                tg.RevisionID,
                tg.Name,
                tg.Year,
                tg.ReleaseType,
                group_concat(t.Name SEPARATOR ' ') AS TagList
            FROM torrents_group tg
            INNER JOIN torrents_tags tt ON (tt.GroupID = tg.ID)
            INNER JOIN tags t ON (t.ID = tt.TagID)
            WHERE tg.ID = ?
            GROUP BY tg.ID, tg.WikiImage, tg.WikiBody, tg.RevisionID, tg.Name, tg.Year, tg.ReleaseType
            ", $Properties['GroupID']
        );
        if ($DB->has_results()) {
            // Don't escape tg.Name. It's written directly to the log table
            [$GroupID, $WikiImage, $WikiBody, $RevisionID, $Properties['Title'], $Properties['Year'], $Properties['ReleaseType'], $TagList]
                = $DB->next_record(MYSQLI_NUM, [4]);
            $Properties['TagList'] = explode(',', str_replace([' ', '.', '_'], '.', $TagList));
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
        foreach ($ArtistForm as $role => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                $DB->prepared_query('
                    SELECT tg.id,
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
                    [$GroupID, $WikiImage, $WikiBody, $RevisionID] = $DB->next_record();
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
                    $DB->prepared_query("
                        SELECT ArtistID, AliasID, Name, Redirect FROM artists_alias WHERE Name = ?
                        ", $Artist['name']
                    );
                    if ($DB->has_results()) {
                        while ([$ArtistID, $AliasID, $AliasName, $Redirect] = $DB->next_record(MYSQLI_NUM, false)) {
                            if (!strcasecmp($Artist['name'], $AliasName)) {
                                if ($Redirect) {
                                    $AliasID = $Redirect;
                                }
                                $ArtistForm[$role][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $AliasName];
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
$artistMan = new Gazelle\Manager\Artist;
if (!$IsNewGroup) {
    $DB->prepared_query('
        UPDATE torrents_group
        SET Time = now()
        WHERE ID = ?
        ', $GroupID
    );
} else {
    if ($isMusicUpload) {
        //array to store which artists we have added already, to prevent adding an artist twice
        $ArtistsAdded = [];
        foreach ($ArtistForm as $role => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                if (!$Artist['id']) {
                    if (isset($ArtistsAdded[strtolower($Artist['name'])])) {
                        $ArtistForm[$role][$Num] = $ArtistsAdded[strtolower($Artist['name'])];
                    } else {
                        [$ArtistID, $AliasID] = $artistMan->create($Artist['name']);
                        $ArtistForm[$role][$Num] = ['id' => $ArtistID, 'aliasid' => $AliasID, 'name' => $Artist['name']];
                        $ArtistsAdded[strtolower($Artist['name'])] = $ArtistForm[$role][$Num];
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
    if ($isMusicUpload) {
        $artistMan->setGroupId($GroupID)->setUserId($LoggedUser['ID']);
        foreach ($ArtistForm as $role => $Artists) {
            foreach ($Artists as $Num => $Artist) {
                $artistMan->addToGroup($Artist['id'], $Artist['aliasid'], $role);
                $Cache->increment('stats_album_count');
            }
        }
    }
    $Cache->increment('stats_group_count');
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
$tagMan = new Gazelle\Manager\Tag;
$tagList = [];
if (!$Properties['GroupID']) {
    foreach ($Properties['TagList'] as $tag) {
        $tag = $tagMan->resolve($tagMan->sanitize($tag));
        if (!empty($tag)) {
            $TagID = $tagMan->create($tag, $LoggedUser['ID']);
            $tagMan->createTorrentTag($TagID, $GroupID, $LoggedUser['ID'], 10);
        }
        $tagList[] = $tag;
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
// (expire the key after 5 minutes to prevent locking it for too long in case there's a fatal error below)
$Cache->cache_value("torrent_{$TorrentID}_lock", true, 300);

$torMan = new Gazelle\Manager\Torrent;
if (in_array($Properties['Encoding'], ['Lossless', '24bit Lossless'])) {
    $torMan->flushLatestUploads(5);
}

//******************************************************************************//
//--------------- Write Log DB       -------------------------------------------//

$ripFiler = new Gazelle\File\RipLog;
$htmlFiler = new Gazelle\File\RipLogHTML;
foreach($logfileSummary->all() as $logfile) {
    $DB->prepared_query('
        INSERT INTO torrents_logs
               (TorrentID, Score, `Checksum`, FileName, Ripper, RipperVersion, `Language`, ChecksumState, LogcheckerVersion, Details)
        VALUES (?,         ?,      ?,         ?,        ?,      ?,             ?,          ?,             ?,                 ?)
        ', $TorrentID, $logfile->score(), $logfile->checksumStatus(), $logfile->filename(),
            $logfile->ripper(), $logfile->ripperVersion(), $logfile->language(), $logfile->checksumState(),
            Logchecker::getLogcheckerVersion(), $logfile->detailsAsString()
    );
    $LogID = $DB->inserted_id();
    $ripFiler->put($logfile->filepath(), [$TorrentID, $LogID]);
    $htmlFiler->put($logfile->text(), [$TorrentID, $LogID]);
}

//******************************************************************************//
//--------------- Write torrent file -------------------------------------------//

$torrentFiler->put($Tor->getEncode(), $TorrentID);
(new Gazelle\Log)->torrent($GroupID, $TorrentID, $LoggedUser['ID'], 'uploaded ('.number_format($TotalSize / (1024 * 1024), 2).' MiB)')
    ->general("Torrent $TorrentID ($LogName) (".number_format($TotalSize / (1024 * 1024), 2).' MiB) was uploaded by ' . $LoggedUser['Username']);

Torrents::update_hash($GroupID);
$Debug->set_flag('upload: sphinx updated');

// Running total for amount of BP to give
$Bonus = new Gazelle\Bonus;
$BonusPoints = $Bonus->getTorrentValue($Properties['Format'], $Properties['Media'], $Properties['Encoding'], $LogInDB,
    $logfileSummary->overallScore(), $logfileSummary->checksumStatus());

//******************************************************************************//
//---------------IRC announce and feeds ---------------------------------------//
$Announce = '';

if ($isMusicUpload) {
    $Announce .= Artists::display_artists($ArtistForm, false);
}
$Announce .= $Properties['Title'] . ' ';
$Details = "";
if ($isMusicUpload) {
    $Announce .= '['.$Properties['Year'].']';
    if ($Properties['ReleaseType'] > 0) {
        $Announce .= ' [' . $releaseTypes[$Properties['ReleaseType']] . ']';
    }
    $Details .= $Properties['Format'].' / '.$Properties['Encoding'];
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
    . " - \00312" . implode(',', $tagList) . "\003"
    . " - \00304".SITE_URL."/torrents.php?id=$GroupID\003 / \00304".SITE_URL."/torrents.php?action=download&id=$TorrentID\003";

// ENT_QUOTES is needed to decode single quotes/apostrophes
Irc::sendRaw('PRIVMSG #ANNOUNCE :'.html_entity_decode($AnnounceSSL, ENT_QUOTES));
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

    $torrentFiler->put($ExtraTorrent['TorEnc'], $ExtraTorrentID);
    $sizeMiB = number_format($ExtraTorrent['TotalSize'] / (1024 * 1024), 2);
    (new Gazelle\Log)->torrent($GroupID, $ExtraTorrentID, $LoggedUser['ID'], "uploaded ($sizeMiB MiB)")
        ->general("Torrent $ExtraTorrentID ($LogName) ($sizeMiB  MiB) was uploaded by " . $LoggedUser['Username']);
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

if (defined('AJAX')) {
    $Response = [
        'groupId' => $GroupID,
        'torrentId' => $TorrentID,
        'private' => !$PublicTorrent,
        'source' => !$UnsourcedTorrent,
    ];

    if ($RequestID) {
        define('NO_AJAX_ERROR', true);
        $FillResponse = require_once(__DIR__ . '/../requests/take_fill.php');
        if (!isset($FillResponse['requestid'])) {
            $FillResponse = [
                'status' => 400,
                'error' => $FillResponse,
            ];
        }
        $Response['fillRequest'] = $FillResponse;
    }
    json_print('success', $Response);
} else {
    if ($PublicTorrent || $UnsourcedTorrent) {
        View::show_header('Warning');
        echo G::$Twig->render('upload/result_warnings.twig', [
            'group_id' => $GroupID,
            'public' => $PublicTorrent,
            'unsourced' => $UnsourcedTorrent,
            'source_flag_wiki_id' => SOURCE_FLAG_WIKI_PAGE_ID,
        ]);
        View::show_footer();
    } elseif ($RequestID) {
        header("Location: requests.php?action=takefill&requestid=$RequestID&torrentid=$TorrentID&auth=".$LoggedUser['AuthKey']);
    } else {
        header("Location: torrents.php?id=$GroupID");
    }
}


if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    ob_flush();
    flush();
    ob_start(); // So we don't keep sending data to the client
}

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->option('AutoSubscribe')) {
    (new Gazelle\Manager\Subscription($user->id()))->subscribeComments('torrents', $GroupID);
}

// Manage notifications
$seenFormatEncoding = [];

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
            $seenFormatEncoding[] = ['format' => $TorrentInfo['Format'], 'bitrate' => $TorrentInfo['Encoding']];
        }
    }
}

$paranoia = unserialize($DB->scalar("
    SELECT Paranoia FROM users_main WHERE ID = ?
    ", $user->id()
)) ?: [];
if (!in_array('notifications', $paranoia)) {
    // For RSS
    $Feed = new Feed;
    $Item = $Feed->item(
        $Title,
        Text::strip_bbcode($Properties['GroupDescription']),
        'torrents.php?action=download&amp;authkey=[[AUTHKEY]]&amp;torrent_pass=[[PASSKEY]]&amp;id=' . $TorrentID,
        $LoggedUser['Username'],
        'torrents.php?id=' . $GroupID,
        implode(',', $tagList)
    );

    (new Gazelle\Notification\Upload)->addFormat($Properties['Format'])
        ->addEncodings($Properties['Encoding'])
        ->addMedia($Properties['Media'])
        ->addYear($Properties['Year'], $Properties['RemasterYear'])
        ->addArtists($torMan->setGroupId($GroupID)->artistRole())
        ->addTags($tagList)
        ->addCategory($Type)
        ->addReleaseType($releaseTypes[$Properties['ReleaseType']])
        ->addUser($user)
        ->setDebug(DEBUG_UPLOAD_NOTIFICATION)
        ->trigger($GroupID, $TorrentID, $Feed, $Item);

    // RSS for bookmarks
    $DB->prepared_query('
        SELECT u.torrent_pass
        FROM users_main AS u
        INNER JOIN bookmarks_torrents AS b ON (b.UserID = u.ID)
        WHERE b.GroupID = ?
        ', $GroupID
    );
    while ([$Passkey] = $DB->next_record()) {
        $feedType[] = "torrents_bookmarks_t_$Passkey";
    }
    foreach ($feedType as $subFeed) {
        $Feed->populate($subFeed, $Item);
    }

    $Debug->set_flag('upload: notifications handled');
}

// Clear cache and allow deletion of this torrent now
$Cache->deleteMulti(["torrents_details_$GroupID", "torrent_{$TorrentID}_lock"]);
if (!$IsNewGroup) {
    $Cache->deleteMulti(["torrent_group_$GroupID", "detail_files_$GroupID"]);
}
