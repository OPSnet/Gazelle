<?php

use OrpheusNET\Logchecker\Logchecker;

ini_set('max_file_uploads', 100);
ini_set('upload_max_filesize', 1_000_000);

define('MAX_FILENAME_LENGTH', 255);
if (!defined('AJAX')) {
    authorize();
}

function reportError(string $message): never {
    if (defined('AJAX')) {
        json_error($message);
    } else {
        // TODO: Repopulate the form correctly
        $Err = $message;
        global $Viewer;
        require(__DIR__ . '/upload.php');
        die();
    }
}

if (!$Viewer->permitted('site_upload')) {
    reportError("Your userclass does not allow you to upload.");
}
if ($Viewer->disableUpload()) {
    reportError('Your upload privileges have been revoked.');
}

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //

$ArtistForm     = [];
$ArtistNameList = [];
$ArtistRoleList = [];
$categoryId     = (int)$_POST['type'] + 1;
$categoryName   = CATEGORY[$categoryId - 1];
$isMusicUpload  = ($categoryName === 'Music');

$Properties = [];
$Properties['Title'] = isset($_POST['title']) ? trim($_POST['title']) : null;
$Properties['Remastered'] = isset($_POST['remaster']);
if ($Properties['Remastered'] || !empty($_POST['unknown'])) {
    $Properties['UnknownRelease']          = !empty($_POST['unknown']) ? 1 : 0;
    $Properties['RemasterYear']            = isset($_POST['remaster_year']) ? (int)$_POST['remaster_year'] : null;
    $_POST['remaster_year']                = $Properties['RemasterYear'];
    $Properties['RemasterTitle']           = trim($_POST['remaster_title'] ?? '');
    $Properties['RemasterRecordLabel']     = trim($_POST['remaster_record_label'] ?? '');
    $Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number'] ?? '');
}
if (!$Properties['Remastered'] || $Properties['UnknownRelease']) {
    $Properties['UnknownRelease']          = 1;
    $Properties['RemasterYear']            = null;
    $Properties['RemasterTitle']           = '';
    $Properties['RemasterRecordLabel']     = '';
    $Properties['RemasterCatalogueNumber'] = '';
}
$Properties['Year'] = isset($_POST['year']) ? (int)$_POST['year'] : null;
$_POST['year'] = $Properties['Year'];
$Properties['RecordLabel'] = trim($_POST['record_label'] ?? '');
$Properties['CatalogueNumber'] = trim($_POST['catalogue_number'] ?? '');
$Properties['ReleaseType'] = $_POST['releasetype'] ?? null;
$Properties['Scene'] = isset($_POST['scene']);
$Properties['Format'] = isset($_POST['format']) ? trim($_POST['format']) : null;
$Properties['Media'] = trim($_POST['media'] ?? '');
$Properties['Encoding'] = trim($_POST['bitrate'] ?? '');
if ($Properties['Encoding'] === 'Other') {
    $_POST['other_bitrate'] = trim($_POST['other_bitrate'] ?? '');
}
$Properties['TagList'] = !empty($_POST['tags'])
    ? array_unique(array_map('trim', explode(',', $_POST['tags']))) // Musicbranes loves to send duplicates
    : [];
$Properties['Image'] = trim($_POST['image'] ?? '');
if ($Properties['Image']) {
    // Strip out Amazon's padding
    if (preg_match('/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i', $Properties['Image'], $match)) {
        $Properties['Image'] = $match[1] . '.jpg';
    }
    if (!preg_match(IMAGE_REGEXP, $Properties['Image'])) {
        reportError(display_str($Properties['Image']) . " does not look like a valid image url");
    }
    $banned = (new Gazelle\Util\ImageProxy($Viewer))->badHost($Properties['Image']);
    if ($banned) {
        reportError("Please rehost images from $banned elsewhere.");
    }
}

$Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
$Properties['Description'] = trim($_POST['release_desc'] ?? '');
if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
} elseif (isset($_POST['desc'])) {
    $Properties['GroupDescription'] = trim($_POST['desc'] ?? '');
}
$Properties['GroupID'] = $_POST['groupid'] ?? null;

if (empty($_POST['artists'])) {
    $Artists = [];
    $Importance = [];
} else {
    $Artists = $_POST['artists'];
    $Importance = $_POST['importance'];
}
if (!empty($_POST['requestid'])) {
    $RequestID = $_POST['requestid'];
    $Properties['RequestID'] = $RequestID;
}

if (!isset($_POST['workaround_broken_html_entities']) || $_POST['workaround_broken_html_entities'] != 0) {
    // upload/edit was submitted with some script or through the API and may contain
    // html entities that need to be unmangled
    $brokenProperties = [ // pretty much everything that is not int/bool
        'Title', 'RemasterTitle', 'RemasterRecordLabel', 'RemasterCatalogueNumber',
        'RecordLabel', 'CatalogueNumber', 'GroupDescription', 'Description'
    ];
    foreach ($brokenProperties as $prop) {
        if (!empty($Properties[$prop])) {
            $Properties[$prop] = html_unescape($Properties[$prop]);
        }
    }
    foreach ($Artists as $idx => $name) {
        $Artists[$idx] = html_unescape($name);
    }
    unset($brokenProperties);
}

//******************************************************************************//
//--------------- Validate data in upload form ---------------------------------//

// common to all types
$Validate = new Gazelle\Util\Validator;
$Validate->setFields([
    ['type', true, 'inarray', 'Please select a valid category.', ['inarray' => array_keys(CATEGORY)]],
    ['release_desc', false, 'string','The release description you entered is too long.', ['maxlength' => 1_000_000]],
    ['rules', true,'require','Your torrent must abide by the rules.'],
]);

if (!$isMusicUpload || !$Properties['GroupID']) {
    $Validate->setFields([
        ['image', false, 'link','The image URL you entered was invalid.', ['range' => [255, 12]]],
        ['tags', true, 'string','You must enter at least one tag. Maximum length is 200 characters.', ['range' => [2, 200]]],
        ['title', true, 'string','Title must be less than 200 characters.', ['maxlength' => 200]],
    ]);
}

if (isset($_POST['album_desc'])) {
    $Validate->setField('album_desc', true, 'string','The album description has a minimum length of 10 characters.', ['range' => [10, 1_000_000]]);
} elseif (isset($_POST['desc'])) {
    $Validate->setField('desc', true, 'string','The description has a minimum length of 10 characters.', ['range' => [10, 1_000_000]]);
}

// audio types
if (in_array($categoryName, ['Music', 'Audiobooks', 'Comedy'])) {
    $Validate->setField('format', true, 'inarray','Please select a valid format.', ['inarray' => FORMAT]);
    if ($Properties['Encoding'] !== 'Other') {
        $Validate->setField('bitrate', true, 'inarray','You must choose a bitrate.', ['inarray' => ENCODING]);
    } else {
        if ($Properties['Format'] === 'FLAC') {
            $Validate->setField('bitrate', true, 'string','FLAC bitrate must be lossless.', ['regex' => '/Lossless/']);
        } else {
            $Validate->setField('other_bitrate',
                true, 'string','You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');;
        }
    }
}

$releaseTypes = (new Gazelle\ReleaseType)->list();
switch ($categoryName) {
    case 'Audiobooks':
        $Validate->setField('year', true,'number','The year of the release must be entered.');
        break;

    case 'Music':
        $Validate->setFields([
            ['groupid', false, 'number', 'Group ID was not numeric'],
            ['media', true, 'inarray','Please select a valid media.', ['inarray' => MEDIA]],
            ['remaster_title', false, 'string','Remaster title must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_record_label', false, 'string','Remaster record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_catalogue_number', false, 'string','Remaster catalogue number must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ]);
        if (!$Properties['GroupID']) {
            $Validate->setFields([
                ['year', true, 'number','The year of the original release must be entered.', ['length' => 40]],
                ['releasetype', true, 'inarray','Please select a valid release type.', ['inarray' => array_keys($releaseTypes)]],
                ['record_label', false, 'string','Record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
                ['catalogue_number', false, 'string','Catalogue Number must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ]);
            if ($Properties['Media'] == 'CD' && !$Properties['Remastered']) {
                $Validate->setField('year', true, 'number', 'You have selected a year for an album that predates the media you say it was created on.', ['minlength' => 1982]);
            }
        }

        if ($Properties['RemasterTitle'] === 'Original Release') {
            $Validate->setField('remaster_title', false, 'string', '"Orginal Release" is not a valid remaster title.');
        }
        if (!$Properties['Remastered']) {
            $Validate->setField('remaster_year', false, 'number','Invalid remaster year.');
        } else {
            if (!$Properties['UnknownRelease']) {
                $Validate->setField('remaster_year', true, 'number','Year of remaster/re-issue must be entered.');
            }
            if ($Properties['Media'] == 'CD' ) {
                $Validate->setField('remaster_year', true, 'number', 'You have selected a year for an album that predates the media you say it was created on.',
                    ['minlength' => 1982]
                );
            }
        }
        break;
}

if (!$Validate->validate($_POST)) {
    reportError($Validate->errorMessage());
}

$File = $_FILES['file_input']; // This is our torrent file
if (substr(strtolower($File['name']), strlen($File['name']) - strlen('.torrent')) !== '.torrent') {
    reportError("You seem to have put something other than a torrent file into the upload field. ({$File['name']}).");
}

$TorrentName = $File['tmp_name'];
if (!is_uploaded_file($TorrentName) || !filesize($TorrentName)) {
    reportError('No torrent file uploaded, or file is empty.');
}

$torMan   = new Gazelle\Manager\Torrent;
$bencoder = new OrpheusNET\BencodeTorrent\BencodeTorrent;
$bencoder->decodeFile($TorrentName);
$PublicTorrent    = $bencoder->makePrivate(); // The torrent is now private.
$UnsourcedTorrent = $torMan->setSourceFlag($bencoder);
$infohash         = $bencoder->getHexInfoHash();
$TorData          = $bencoder->getData();
if (isset($TorData['encrypted_files'])) {
    reportError('This torrent contains an encrypted file list which is not supported here.');
}
if (isset($TorData['info']['meta version'])) {
    reportError('This torrent is not a V1 torrent. V2 and Hybrid torrents are not supported here.');
}

$checker     = new Gazelle\Util\FileChecker;
$DirName     = (isset($TorData['info']['files']) ? make_utf8($bencoder->getName()) : '');
$checkName   = $checker->checkName($DirName); // check the folder name against the blacklist
if ($checkName) {
    reportError($checkName);
}

$upload = [
    'file'  => [], // details of logfiles in $_FILES
    'extra' => [], // details of the extra encodings
    'new'   => [], // list of newly created Torrent objects
];

$torrentFiler = new Gazelle\File\Torrent;
$torrent      = $torMan->findByInfohash(bin2hex($bencoder->getHexInfoHash()));
if ($torrent) {
    $torrentId = $torrent->id();
    if ($torrentFiler->exists($torrentId)) {
        reportError(
            defined('AJAX')
            ? "The exact same torrent file already exists on the site! (torrentid=$torrentId)"
            : "<a href=\"torrents.php?torrentid=$torrentId\">The exact same torrent file already exists on the site!</a>"
       );
    } else {
        // A lost torrent
        $torrentFiler->put($bencoder->getEncode(), $torrentId);
        reportError(
            defined('AJAX')
            ? "Thank you for fixing this torrent (torrentid=$torrentId)"
            : "<a href=\"torrents.php?torrentid=$torrentId\">Thank you for fixing this torrent</a>"
        );
    }
}

if ($isMusicUpload) {
    // additional torrent files
    $dupeName = [$_FILES['file_input']['name'] => true];
    if (!empty($_POST['extra_format']) && !empty($_POST['extra_bitrate'])) {
        for ($i = 1; $i <= 5; $i++) {
            if (empty($_FILES["extra_file_$i"])) {
                continue;
            }
            $filename    = $_FILES["extra_file_$i"];
            $fileTmpName = (string)$filename['tmp_name'];
            if (!is_uploaded_file($fileTmpName) || !filesize($fileTmpName)) {
                reportError('No extra torrent file uploaded, or file is empty.');
            } elseif (substr(strtolower($filename['name']), strlen($filename['name']) - strlen('.torrent')) !== '.torrent') {
                reportError("You seem to have put something other than an extra torrent file into the upload field. ({$filename['name']}).");
            } elseif (isset($DupeName[$filename['name']])) {
                reportError('One or more torrents has been entered into the form twice.');
            }
            $dupeName[$filename['name']] = true;

            $format = trim($_POST['extra_format'][$i - 1]);
            if (empty($format)) {
                reportError('Missing format for extra torrent.');
            }
            $encoding = trim($_POST['extra_bitrate'][$i - 1]);
            if (empty($encoding)) {
                reportError('Missing encoding/bitrate for extra torrent.');
            }

            $xbencoder = new OrpheusNET\BencodeTorrent\BencodeTorrent;
            $xbencoder->decodeFile($fileTmpName);
            $ExtraTorData = $xbencoder->getData();
            if (isset($ExtraTorData['encrypted_files'])) {
                reportError('At least one of the torrents contain an encrypted file list which is not supported here');
            }

            $torrent = $torMan->findByInfohash(bin2hex($xbencoder->getHexInfoHash()));
            if ($torrent) {
                $torrentId = $torrent->id();
                if ($torrentFiler->exists($torrentId)) {
                    reportError(
                        defined('AJAX')
                        ? "The exact same torrent file already exists on the site! (torrentid=$torrentId)"
                        : "<a href=\"torrents.php?torrentid=$torrentId\">The exact same torrent file already exists on the site!</a>"
                    );
                } else {
                    $torrentFiler->put($ExtraTorData['TorEnc'], $torrentId);
                    reportError(
                        defined('AJAX')
                        ? "Thank you for fixing this torrent (torrentid=$torrentId)"
                        : "<a href=\"torrents.php?torrentid=$torrentId\">Thank you for fixing this torrent</a>"
                    );
                }
            }
            if (!$xbencoder->isPrivate()) {
                $xbencoder->makePrivate(); // The torrent is now private.
                $PublicTorrent = true;
            }
            if ($torMan->setSourceFlag($xbencoder)) {
                $UnsourcedTorrent = true;
            }

            // File list and size
            $filePath = isset($ExtraTorData['info']['files']) ? make_utf8($xbencoder->getName()) : '';
            $fileList = [];
            ['total_size' => $totalSize, 'files' => $ExtraFileList] = $xbencoder->getFileList();
            foreach ($ExtraFileList as ['path' => $name, 'size' => $size]) {
                $checkFile = $checker->checkFile($categoryName, $name);
                if ($checkFile) {
                    reportError($checkFile);
                }
                if (mb_strlen($name, 'UTF-8') + mb_strlen($filePath, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
                    $fullpath = "$filePath/$name";
                    reportError(
                        defined('AJAX')
                            ? "The torrent contained one or more files with too long a name: $fullpath"
                            : "The torrent contained one or more files with too long a name: <br />$fullpath"
                    );
                }
                $fileList[] = $torMan->metaFilename($name, $size);
            }
            $upload['extra'][] = [
                'Description' => trim($_POST['extra_release_desc'][$i - 1]),
                'Encoding'    => $encoding,
                'FileList'    => $fileList,
                'FilePath'    => $filePath,
                'Format'      => $format,
                'InfoHash'    => $xbencoder->getHexInfoHash(),
                'Name'        => $fileTmpName,
                'TorEnc'      => $xbencoder->getEncode(),
                'TotalSize'   => $totalSize,
            ];
        }
    }

    // Multiple artists
    if (empty($Properties['GroupID'])) {
        $ArtistForm = [
            ARTIST_MAIN      => [],
            ARTIST_GUEST     => [],
            ARTIST_REMIXER   => [],
            ARTIST_COMPOSER  => [],
            ARTIST_CONDUCTOR => [],
            ARTIST_DJ        => [],
            ARTIST_PRODUCER  => [],
            ARTIST_ARRANGER  => [],
        ];
        $ArtistNameByRole = [
            ARTIST_MAIN      => [],
            ARTIST_GUEST     => [],
            ARTIST_REMIXER   => [],
            ARTIST_COMPOSER  => [],
            ARTIST_CONDUCTOR => [],
            ARTIST_DJ        => [],
            ARTIST_PRODUCER  => [],
            ARTIST_ARRANGER  => [],
        ];
        $ArtistRoleList = [];
        $ArtistNameList = [];
        for ($i = 0, $end = count($Artists); $i < $end; $i++) {
            $name = Gazelle\Artist::sanitize($Artists[$i]);
            if ($name === '') {
                continue;
            }
            $role = (int)$Importance[$i];
            if (!in_array($name, $ArtistNameByRole[$role])) {
                $ArtistNameByRole[$role][] = $name;
                $ArtistForm[$role][] = ['name' => $name];
                $ArtistRoleList[] = $role;
                $ArtistNameList[] = $name;
            }
        }
        if (empty($ArtistNameByRole[ARTIST_MAIN])) {
            reportError('Please enter at least one main artist');
        }
    }
}

//******************************************************************************//
//--------------- Generate torrent file ----------------------------------------//

// File list and size
['total_size' => $TotalSize, 'files' => $FileList] = $bencoder->getFileList();
$hasLog       = false;
$hasCue       = false;
$TmpFileList  = [];
$TooLongPaths = [];

foreach ($FileList as ['path' => $filename, 'size' => $size]) {
    if ($Properties['Encoding'] == "Lossless" && preg_match('/\.cue$/i', $filename)) {
        $hasCue = true;
    }
    if ($Properties['Media'] == 'CD'
        && $Properties['Encoding'] == "Lossless"
        && !in_array(strtolower($filename), IGNORE_AUDIO_LOGFILE)
        && preg_match('/\.log$/i', $filename)
    ) {
        $hasLog = true;
    }
    $checkName = $checker->checkFile($categoryName, $filename);
    if ($checkName) {
        reportError($checkName);
    }
    if (mb_strlen($filename, 'UTF-8') + mb_strlen($DirName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
        $TooLongPaths[] = "$DirName/$filename";
    }
    $TmpFileList[] = $torMan->metaFilename($filename, $size);
}
if (count($TooLongPaths) > 0) {
    reportError(
        defined('AJAX')
        ? (string)json_encode(
            ['The torrent contained one or more files with too long a name', ['list' => $TooLongPaths]],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        )
        : ('The torrent contained one or more files with too long a name: <ul>'
            . implode('', array_map(fn($p) => "<li>$p</li>", $TooLongPaths))
            . '</ul><br />')
    );
}
$Debug->set_flag('upload: torrent decoded');

$tgMan      = new Gazelle\Manager\TGroup;
$tgroup     = null;
$NoRevision = false;

if ($isMusicUpload) {
    // Does it belong in a group?
    if ($Properties['GroupID']) {
        $tgroup = $tgMan->findById($Properties['GroupID']);
    }
    if (is_null($tgroup)) {
        foreach ($ArtistForm[ARTIST_MAIN] as $Artist) {
            $tgroup = $tgMan->findByArtistReleaseYear($Artist['name'], $Properties['Title'], $Properties['ReleaseType'], $Properties['Year']);
            if ($tgroup) {
                break;
            }
        }
    }
    if ($tgroup) {
        $Properties['ReleaseType'] = $tgroup->releaseType();
        $Properties['Year']        = $tgroup->year();
        $Properties['TagList']     = $tgroup->tagNameList();
        if (!$Properties['Image'] && $tgroup->image()) {
            $Properties['Image'] = $tgroup->image();
        }
        if ($Properties['GroupDescription'] != $tgroup->description()) {
            $Properties['GroupDescription'] = $tgroup->description();
            if (!$Properties['Image'] || $Properties['Image'] == $tgroup->image()) {
                $NoRevision = true;
            }
        }
    }
}

//For notifications--take note now whether it's a new group
$IsNewGroup = is_null($tgroup);

$logfileSummary = ($hasLog && isset($_FILES['logfiles']))
    ? new Gazelle\LogfileSummary($_FILES['logfiles'])
    : null;
$hasLogInDB = $logfileSummary?->total() > 0;

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$log = new Gazelle\Log;
$Debug->set_flag('upload: database begin transaction');
$db = Gazelle\DB::DB();
$db->begin_transaction();

if ($tgroup) {
    $tgroup->touch();
} else {
    $tgroup = $tgMan->create(
        categoryId:      $categoryId,
        name:            $Properties['Title'],
        year:            $Properties['Year'],
        recordLabel:     $Properties['RecordLabel'],
        catalogueNumber: $Properties['CatalogueNumber'],
        description:     $Properties['GroupDescription'],
        image:           $Properties['Image'],
        releaseType:     $Properties['ReleaseType'],
        showcase:        (bool)($Viewer->permitted('torrents_edit_vanityhouse') && isset($_POST['vanity_house'])),
    );
    if ($isMusicUpload) {
        $tgroup->addArtists($ArtistRoleList, $ArtistNameList, $Viewer, new Gazelle\Manager\Artist, $log);
        $Cache->increment_value('stats_album_count', count($ArtistNameList));
    }
    $Viewer->stats()->increment('unique_group_total');
}
$GroupID = $tgroup->id();
$logName = $tgroup->text();

// Description
if ($NoRevision) {
    $tgroup->createRevision($Properties['GroupDescription'], $Properties['Image'], 'Uploaded new torrent', $Viewer);
}

// Tags
$tagMan = new Gazelle\Manager\Tag;
if (!$Properties['GroupID']) {
    foreach ($Properties['TagList'] as $tag) {
        $tag = $tagMan->resolve($tagMan->sanitize($tag));
        if (!empty($tag)) {
            $tagMan->createTorrentTag($tagMan->create($tag, $Viewer), $GroupID, $Viewer->id(), 10);
        }
    }
}

// Torrent
$torrent = $torMan->create(
    tgroup:                  $tgroup,
    user:                    $Viewer,
    description:             $Properties['Description'],
    media:                   $Properties['Media'],
    format:                  $Properties['Format'],
    encoding:                $Properties['Encoding'],
    logScore:                $logfileSummary?->overallScore() ?? 0,
    infohash:                $infohash,
    filePath:                $DirName,
    fileList:                $TmpFileList,
    size:                    $TotalSize,
    isScene:                 $Properties['Scene'],
    isRemaster:              $Properties['Remastered'],
    remasterYear:            $Properties['RemasterYear'],
    remasterTitle:           $Properties['RemasterTitle'],
    remasterRecordLabel:     $Properties['RemasterRecordLabel'],
    remasterCatalogueNumber: $Properties['RemasterCatalogueNumber'],
    hasChecksum:             $logfileSummary?->checksum() ?? false,
    hasCue:                  $hasCue,
    hasLog:                  $hasLog,
    hasLogInDB:              $hasLogInDB,
);
$TorrentID       = $torrent->id();
$upload['new'][] = $torrent;

//******************************************************************************//
//--------------- Upload Extra torrents ----------------------------------------//

foreach ($upload['extra'] as $info) {
    $extra = $torMan->create(
        tgroup:                  $tgroup,
        user:                    $Viewer,
        media:                   $Properties['Media'],
        isScene:                 $Properties['Scene'],
        isRemaster:              $Properties['Remastered'],
        remasterYear:            $Properties['RemasterYear'],
        remasterTitle:           $Properties['RemasterTitle'],
        remasterRecordLabel:     $Properties['RemasterRecordLabel'],
        remasterCatalogueNumber: $Properties['RemasterCatalogueNumber'],
        description:             $info['Description'],
        format:                  $info['Format'],
        encoding:                $info['Encoding'],
        infohash:                $info['InfoHash'],
        filePath:                $info['FilePath'],
        fileList:                $info['FileList'],
        size:                    $info['TotalSize'],
    );

    $size            = number_format($extra->size() / (1024 * 1024), 2);
    $upload['new'][] = $extra;
    $torrentFiler->put($info['TorEnc'], $extra->id());
    $log->torrent($GroupID, $extra->id(), $Viewer->id(), "uploaded ($size MiB)")
        ->general("Torrent {$extra->id()} ($logName) ($size MiB) was uploaded by " . $Viewer->username());
}

//******************************************************************************//
//--------------- Write Files To Disk ------------------------------------------//

if ($logfileSummary?->total()) {
    $torrentLogManager = new Gazelle\Manager\TorrentLog(new Gazelle\File\RipLog, new Gazelle\File\RipLogHTML);
    $checkerVersion = Logchecker::getLogcheckerVersion();
    foreach ($logfileSummary->all() as $logfile) {
        $torrentLogManager->create($torrent, $logfile, $checkerVersion);
    }
}

$size = number_format($TotalSize / (1024 * 1024), 2);
$log->torrent($GroupID, $TorrentID, $Viewer->id(), "uploaded ($size MiB)")
    ->general("Torrent $TorrentID ($logName) ($size MiB) was uploaded by " . $Viewer->username());

if (!$torrentFiler->put($bencoder->getEncode(), $TorrentID)) {
    reportError("Internal error saving torrent file. Please report this in the bugs forum.");
}
$db->commit(); // We have a usable upload, any subsequent failures can be repaired ex post facto
$Debug->set_flag('upload: database committed');

//******************************************************************************//
//--------------- Finalize -----------------------------------------------------//

$bonusTotal  = 0;
$bonus       = new Gazelle\User\Bonus($Viewer);
$tracker     = new Gazelle\Tracker;
$folderCheck = [];
foreach ($upload['new'] as $t) {
    $t->flush()->unlockUpload();
    $bonusTotal += $bonus->torrentValue($t);
    $tracker->addTorrent($t);
    $folderCheck[] = $t->path();
}
(new Gazelle\Manager\NotificationTicket)->create($torrent);

if (!$Viewer->disableBonusPoints()) {
    $bonus->addPoints($bonusTotal);
}

$tgroup->refresh();

if ($Viewer->option('AutoSubscribe')) {
    (new Gazelle\User\Subscription($Viewer))->subscribeComments('torrents', $GroupID);
}

$totalNew = count($upload['new']);
$Viewer->stats()->increment('upload_total', $totalNew);
if ($torrent->isPerfectFlac()) {
    $Viewer->stats()->increment('perfect_flac_total');
} elseif ($torrent->isPerfecterFlac()) {
    $Viewer->stats()->increment('perfecter_flac_total');
}

// Update the various cache keys affected by this
$Cache->increment_value('stats_torrent_count', $totalNew);
if ($Properties['Image'] != '') {
    $Cache->delete_value('user_recent_up_' . $Viewer->id());
}

if (defined('AJAX')) {
    $Response = [
        'groupId' => $GroupID,
        'torrentId' => $TorrentID,
        'private' => !$PublicTorrent,
        'source' => !$UnsourcedTorrent,
    ];

    if (isset($RequestID)) {
        define('NO_AJAX_ERROR', true);
        $_REQUEST['torrentid'] = $TorrentID;
        $FillResponse = require_once(__DIR__ . '/../requests/take_fill.php');
        if (!isset($FillResponse['requestId'])) {
            $FillResponse = [
                'status' => 400,
                'error' => $FillResponse,
            ];
        }
        $Response['fillRequest'] = $FillResponse;
    }
    json_print('success', $Response);
    exit;
}

$folderClash = 0;
if ($isMusicUpload) {
    foreach ($folderCheck as $foldername) {
        // This also has the nice side effect of warming the cache immediately
        if (count($torMan->findAllByFoldername($foldername)) > 1) {
            ++$folderClash;
        }
    }
}
if ($PublicTorrent || $UnsourcedTorrent || $folderClash) {
    echo $Twig->render('upload/result_warnings.twig', [
        'clash'     => $folderClash,
        'group_id'  => $GroupID,
        'public'    => $PublicTorrent,
        'unsourced' => $UnsourcedTorrent,
        'wiki_id'   => SOURCE_FLAG_WIKI_PAGE_ID,
    ]);
} elseif (isset($RequestID)) {
    header("Location: requests.php?action=takefill&requestid=$RequestID&torrentid=$TorrentID&auth=" . $Viewer->auth());
} else {
    header("Location: torrents.php?id=$GroupID");
}
