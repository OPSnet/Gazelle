<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */
/** @phpstan-var \Gazelle\Debug $Debug */

declare(strict_types=1);

namespace Gazelle;

use OrpheusNET\Logchecker\Logchecker;

ini_set('max_file_uploads', 100);
ini_set('upload_max_filesize', 1_000_000);

define('MAX_FILENAME_LENGTH', 255);
if (!defined('AJAX')) {
    authorize();
}

if (!$Viewer->permitted('site_upload')) {
    json_error("Your userclass does not allow you to upload.");
}
if ($Viewer->disableUpload()) {
    json_error('Your upload privileges have been revoked.');
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
$Properties['Artists'] = $ArtistForm;
$Properties['Title'] = isset($_POST['title']) ? trim($_POST['title']) : null;
if (isset($_POST['unknown'])) {
    $Properties['UnknownRelease']          = true;
    $Properties['RemasterYear']            = null;
    $Properties['RemasterTitle']           = '';
    $Properties['RemasterRecordLabel']     = '';
    $Properties['RemasterCatalogueNumber'] = '';
} else {
    $Properties['UnknownRelease']          = false;
    $Properties['RemasterYear']            = (int)($_POST['remaster_year'] ?? 0);
    $Properties['RemasterTitle']           = trim($_POST['remaster_title'] ?? '');
    $Properties['RemasterRecordLabel']     = trim($_POST['remaster_record_label'] ?? '');
    $Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number'] ?? '');
}
$Properties['Year'] = isset($_POST['year']) ? (int)$_POST['year'] : null;
$_POST['year'] = $Properties['Year'];
$Properties['ReleaseType'] = (int)($_POST['releasetype'] ?? 0);
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
$Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
$Properties['Description'] = trim($_POST['release_desc'] ?? '');
if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = trim($_POST['album_desc'] ?? '');
} elseif (isset($_POST['desc'])) {
    $Properties['GroupDescription'] = trim($_POST['desc'] ?? '');
}
$Properties['GroupID'] = (int)($_POST['groupid'] ?? 0);

if (empty($_POST['artists'])) {
    $Artists = [];
    $artistRole = [];
} else {
    $Artists = $_POST['artists'];
    $artistRole = array_map('intval', $_POST['importance']);
}

if (!empty($_POST['requestid'])) {
    $RequestID = (int)$_POST['requestid'];
    $Properties['RequestID'] = $RequestID;
}

if (!isset($_POST['workaround_broken_html_entities']) || $_POST['workaround_broken_html_entities'] != 0) {
    // upload/edit was submitted with some script or through the API and may contain
    // html entities that need to be unmangled
    $brokenProperties = [ // pretty much everything that is not int/bool
        'Title', 'RemasterTitle', 'RemasterRecordLabel', 'RemasterCatalogueNumber',
        'GroupDescription', 'Description'
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
$Validate = new Util\Validator();
$Validate->setFields([
    ['type', true, 'inarray', 'Please select a valid category.', ['inarray' => array_keys(CATEGORY)]],
    ['release_desc', false, 'string','The release description you entered is too long.', ['maxlength' => 1_000_000]],
]);

if (!$isMusicUpload || !$Properties['GroupID']) {
    $Validate->setFields([
        ['image', false, 'link','The image URL you entered was invalid.', ['range' => [255, 12]]],
        ['tags', true, 'string','You must enter at least one tag. Maximum length is 200 characters.', ['range' => [2, 200]]],
        ['title', true, 'string','Title must be less than 200 characters.', ['maxlength' => 200]],
    ]);
}

if (isset($_POST['album_desc'])) {
    $Validate->setField('album_desc', true, 'string', 'The album description has a minimum length of 10 characters.', ['range' => [10, 1_000_000]]);
} elseif (isset($_POST['desc'])) {
    $Validate->setField('desc', true, 'string', 'The description has a minimum length of 10 characters.', ['range' => [10, 1_000_000]]);
}

// audio types
if (in_array($categoryName, ['Music', 'Audiobooks', 'Comedy'])) {
    $Validate->setField('format', true, 'inarray', 'Please select a valid format.', ['inarray' => FORMAT]);
    if ($Properties['Encoding'] !== 'Other') {
        $Validate->setField('bitrate', true, 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]);
    } else {
        if ($Properties['Format'] === 'FLAC') {
            $Validate->setField('bitrate', true, 'string', 'FLAC bitrate must be lossless.', ['regex' => '/Lossless/']);
        } else {
            $Validate->setField('other_bitrate', true, 'string', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');
        }
    }
}

$nextYear = (int)date('Y') + 1;
if ($categoryName === 'Music') {
    $Validate->setFields([
        ['groupid', false, 'number', 'Group ID was not numeric'],
        ['media', true, 'inarray','Please select a valid media.', ['inarray' => MEDIA]],
        ['remaster_title', false, 'string','Remaster title must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ['remaster_record_label', false, 'string','Remaster record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ['remaster_catalogue_number', false, 'string','Remaster catalogue number must be between 1 and 80 characters.', ['range' => [1, 80]]],
    ]);
    if (!$Properties['GroupID']) {
        $Validate->setFields([
            ['year', true, 'number','The year of the original release must be entered.', ['maxlength' => $nextYear]],
            ['releasetype', true, 'inarray','Please select a valid release type.', ['inarray' => array_keys(new ReleaseType()->list())]],
            ['record_label', false, 'string','Record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['catalogue_number', false, 'string','Catalogue Number must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ]);
    }
    if ($Properties['RemasterTitle'] === 'Original Release') {
        $Validate->setField('remaster_title', false, 'string', '"Orginal Release" is not a valid remaster title.');
    }
    if (!$Properties['UnknownRelease']) {
        if (($_POST['remaster_year'] ?? '') === '') {
            json_error('You must specify at minimum the edition year. Label and catalogue number are highly encouraged.');
        }
        if ($Properties['Media'] === 'CD') {
            $Validate->setField('remaster_year', true, 'number',
                'You have selected a year for an album that predates the media you say it was created on.',
                ['minlength' => 1982, 'maxlength' => $nextYear]
            );
        } else {
            $Validate->setField('remaster_year', true, 'number',
                'The year of the edition must be entered.', ['maxlength' => $nextYear]);
        }
    }
} elseif (in_array($categoryName, ['Audiobooks', 'Comedy'], true)) {
    $Validate->setField('year', true, 'number',
        'The year of the release must be entered.', ['maxlength' => $nextYear]);
}

if ($isMusicUpload && !$Properties['GroupID']) {
    $artistMan = new Manager\Artist();
    if (count($Artists) !== count($artistRole)) {
        json_error("There is an error with how artists are specified.");
    }
    // Multiple artists
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
    for ($i = 0, $end = count($Artists); $i < $end; $i++) {
        $name = Artist::sanitize($Artists[$i]);
        if ($name === '') {
            continue;
        }
        $roleId = $artistRole[$i];
        if (!$artistMan->roleExists($categoryId, $roleId)) {
            // ignore bogus artist role
            continue;
        }
        if (!in_array($name, $ArtistNameByRole[$roleId])) {
            $ArtistNameByRole[$roleId][] = $name;
            $ArtistForm[$roleId][] = ['name' => $name];
            $ArtistRoleList[] = $roleId;
            $ArtistNameList[] = $name;
        }
    }
    $Properties['Artists'] = $ArtistForm;
    if (empty($ArtistNameByRole[ARTIST_MAIN])) {
        json_error('Please enter at least one main artist');
    }
}

if (!$Validate->validate($_POST)) {
    json_error($Validate->errorMessage());
}

if ($Properties['Image']) {
    // Strip out Amazon's padding
    if (preg_match('/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i', $Properties['Image'], $match)) {
        $Properties['Image'] = $match[1] . '.jpg';
    }
    if (!preg_match(IMAGE_REGEXP, $Properties['Image'])) {
        json_error(display_str($Properties['Image']) . " does not look like a valid image url");
    }
    $banned = Util\ImageProxy::badHost($Properties['Image']);
    if ($banned) {
        json_error("Please rehost images from $banned elsewhere.");
    }
}

$File = $_FILES['file_input']; // This is our torrent file
if (substr(strtolower($File['name']), strlen($File['name']) - strlen('.torrent')) !== '.torrent') {
    json_error("You seem to have put something other than a torrent file into the upload field. ({$File['name']}).");
}

$TorrentName = $File['tmp_name'];
if (!is_uploaded_file($TorrentName) || !filesize($TorrentName)) {
    json_error('No torrent file uploaded, or file is empty.');
}

$torMan   = new Manager\Torrent();
$bencoder = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
try {
    $bencoder->decodeFile($TorrentName);
} catch (\RuntimeException $e) {
    if ($e->getMessage() == "Could not fully decode bencode string") {
        json_error("The torrent file '{$File['name']}' is corrupted, please recreate it");
    }
}
$PublicTorrent    = $bencoder->makePrivate(); // The torrent is now private.
$UnsourcedTorrent = $torMan->setSourceFlag($bencoder);
$infohash         = $bencoder->getHexInfoHash();
$TorData          = $bencoder->getData();
if (isset($TorData['encrypted_files'])) {
    json_error('This torrent contains an encrypted file list which is not supported here.');
}
if (isset($TorData['info']['meta version'])) {
    json_error('This torrent is not a V1 torrent. V2 and Hybrid torrents are not supported here.');
}

$checker    = new Util\FileChecker();
$folderName = (isset($TorData['info']['files']) ? make_utf8($bencoder->getName()) : '');
if ($isMusicUpload && $folderName === '') {
    json_error("Music uploads must be in a folder ({$File['name']})");
}
$checkName = $checker->checkName($folderName); // check the folder name against the blacklist
if ($checkName) {
    json_error($checkName);
}

$upload = [
    'extra' => [], // details of the extra encodings
    'new'   => [], // list of newly created Torrent objects
];

$torrent = $torMan->findByInfohash(bin2hex($bencoder->getHexInfoHash()));
if ($torrent) {
    $torrentFile = new File\Torrent($torrent->id);
    if ($torrentFile->exists()) {
        json_error("The exact same torrent file already exists on the site! {$torrent->link()}");
    } else {
        // A lost torrent
        $torrentFile->put($bencoder->getEncode());
        json_error("Thank you for fixing this torrent {$torrent->link()}");
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
                json_error('No extra torrent file uploaded, or file is empty.');
            } elseif (substr(strtolower($filename['name']), strlen($filename['name']) - strlen('.torrent')) !== '.torrent') {
                json_error("You seem to have put something other than an extra torrent file into the upload field. ({$filename['name']}).");
            } elseif (isset($DupeName[$filename['name']])) {
                json_error('One or more torrents has been entered into the form twice.');
            }
            $dupeName[$filename['name']] = true;

            $format = trim($_POST['extra_format'][$i - 1]);
            if (empty($format)) {
                json_error('Missing format for extra torrent.');
            }
            $encoding = trim($_POST['extra_bitrate'][$i - 1]);
            if (empty($encoding)) {
                json_error('Missing encoding/bitrate for extra torrent.');
            }

            $xbencoder = new \OrpheusNET\BencodeTorrent\BencodeTorrent();
            try {
                $xbencoder->decodeFile($fileTmpName);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() == "Could not fully decode bencode string") {
                    json_error("The torrent file '{$filename}' is corrupted, please recreate it");
                }
            }
            $ExtraTorData = $xbencoder->getData();
            if (isset($ExtraTorData['encrypted_files'])) {
                json_error('At least one of the torrents contain an encrypted file list which is not supported here');
            }

            $torrent = $torMan->findByInfohash(bin2hex($xbencoder->getHexInfoHash()));
            if ($torrent) {
                $torrentFile = new File\Torrent($torrent->id);
                if ($torrentFile->exists()) {
                    json_error("The exact same torrent file already exists on the site! {$torrent->link()}");
                } else {
                    // A lost torrent
                    $torrentFile->put($bencoder->getEncode());
                    json_error("Thank you for fixing this torrent {$torrent->link()}");
                }
            }
            $isPrivate = $xbencoder->isPrivate();
            if (!$isPrivate) {
                $xbencoder->makePrivate(); // The torrent is now private.
                $PublicTorrent = true;
            }
            $unsourced = $torMan->setSourceFlag($xbencoder);
            if ($unsourced) {
                $UnsourcedTorrent = true;
            }

            // File list and size
            $filePath = isset($ExtraTorData['info']['files']) ? make_utf8($xbencoder->getName()) : '';
            if ($filePath === '') {
                json_error("Music uploads must be in a folder ($filename)");
            }
            $fileList = [];
            ['total_size' => $totalSize, 'files' => $ExtraFileList] = $xbencoder->getFileList();
            foreach ($ExtraFileList as ['path' => $name, 'size' => $size]) {
                $checkFile = $checker->checkFile($categoryName, $name);
                if ($checkFile) {
                    json_error($checkFile);
                }
                if (mb_strlen($name, 'UTF-8') + mb_strlen($filePath, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
                    $fullpath = "$filePath/$name";
                    json_error("The torrent contained one or more files with too long a name: " . html_escape($fullpath));
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
                'Private'     => $isPrivate,
                'HasSource'   => !$unsourced,
            ];
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
    if (
        $Properties['Media'] == 'CD'
        && $Properties['Encoding'] == "Lossless"
        && !in_array(strtolower($filename), IGNORE_AUDIO_LOGFILE)
        && preg_match('/\.log$/i', $filename)
    ) {
        $hasLog = true;
    }
    $checkName = $checker->checkFile($categoryName, $filename);
    if ($checkName) {
        json_error($checkName);
    }
    if (mb_strlen($filename, 'UTF-8') + mb_strlen($folderName, 'UTF-8') + 1 > MAX_FILENAME_LENGTH) {
        $TooLongPaths[] = "$folderName/$filename";
    }
    $TmpFileList[] = $torMan->metaFilename($filename, $size);
}
if (count($TooLongPaths) > 0) {
    json_error('The torrent contained one or more files with too long a name: <ul>'
            . implode('', array_map(fn($p) => "<li>" . html_escape($p) . "</li>", $TooLongPaths))
            . '</ul><br>'
    );
}
$Debug->mark('upload: torrent decoded');

$tgMan  = new Manager\TGroup();
$tgroup = null;

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
}

//For notifications--take note now whether it's a new group
$IsNewGroup = is_null($tgroup);

$logfileSummary = ($hasLog && isset($_FILES['logfiles']))
    ? new LogfileSummary($_FILES['logfiles'])
    : null;
$hasLogInDB = $logfileSummary?->total() > 0;

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$Debug->mark('upload: database begin transaction');
$db = DB::DB();
$db->begin_transaction();

if ($tgroup) {
    $tgroup->touch();
} else {
    $tgroup = $tgMan->create(
        categoryId:      $categoryId,
        name:            $Properties['Title'],
        year:            $Properties['Year'],
        description:     $Properties['GroupDescription'],
        image:           $Properties['Image'],
        releaseType:     $Properties['ReleaseType'],
        showcase:        $Viewer->permitted('torrents_edit_vanityhouse') && isset($_POST['vanity_house']),
    );

    // Tags
    $tagMan = new Manager\Tag();
    foreach ($Properties['TagList'] as $name) {
        $tagMan->softCreate($name, $Viewer)?->addTGroup($tgroup, $Viewer, 10);
    }

    if ($isMusicUpload) {
        $tgroup->addArtists($ArtistRoleList, $ArtistNameList);
        $Cache->increment_value('stats_album_count', count($ArtistNameList));
    }
    $Viewer->stats()->increment('unique_group_total');
}
$GroupID = $tgroup->id;
$logName = $tgroup->text();

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
    filePath:                $folderName,
    fileList:                $TmpFileList,
    size:                    $TotalSize,
    isScene:                 $Properties['Scene'],
    remasterYear:            $Properties['RemasterYear'],
    remasterTitle:           $Properties['RemasterTitle'],
    remasterRecordLabel:     $Properties['RemasterRecordLabel'],
    remasterCatalogueNumber: $Properties['RemasterCatalogueNumber'],
    hasChecksum:             $logfileSummary?->checksum() ?? false,
    hasCue:                  $hasCue,
    hasLog:                  $hasLog,
    hasLogInDB:              $hasLogInDB,
);
$TorrentID       = $torrent->id;
$upload['new'][] = $torrent;

//******************************************************************************//
//--------------- Upload Extra torrents ----------------------------------------//

foreach ($upload['extra'] as &$info) {
    $extra = $torMan->create(
        tgroup:                  $tgroup,
        user:                    $Viewer,
        media:                   $Properties['Media'],
        isScene:                 $Properties['Scene'],
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
    $info['TorrentID'] = $extra->id;
    $size              = number_format($extra->size() / (1024 * 1024), 2);
    $upload['new'][]   = $extra;
    new File\Torrent($extra->id)->put($info['TorEnc']);
    $extra->logger()->torrent($extra, $Viewer, "uploaded ($size MiB)")
        ->general("Torrent {$extra->id} ($logName) ($size MiB) was uploaded by " . $Viewer->username());
}
unset($info);

//******************************************************************************//
//--------------- Write Files To Disk ------------------------------------------//

if ($logfileSummary?->total()) {
    $torrentLogManager = new Manager\TorrentLog();
    $checkerVersion = Logchecker::getLogcheckerVersion();
    foreach ($logfileSummary->all() as $logfile) {
        $torrentLogManager->create($torrent, $logfile, $checkerVersion);
    }
}

$size = number_format($TotalSize / (1024 * 1024), 2);
$torrent->logger()->torrent($torrent, $Viewer, "uploaded ($size MiB)")
    ->general("Torrent $TorrentID ($logName) ($size MiB) was uploaded by " . $Viewer->username());

if (!new File\Torrent($TorrentID)->put($bencoder->getEncode())) {
    json_error("Internal error saving torrent file. Please report this in the bugs forum.");
}
$db->commit(); // We have a usable upload, any subsequent failures can be repaired ex post facto
$Debug->mark('upload: database committed');

//******************************************************************************//
//--------------- Finalize -----------------------------------------------------//

$tracker      = new Tracker();
$uploadReward = new BonusUploadReward();
$bonusTotal   = $uploadReward->boost($Viewer);
if ($bonusTotal > 0) {
    $Viewer->addStaffNote("$bonusTotal points boost for {$torrent->publicLocation()}")->modify();
}
$folderCheck  = [];
foreach ($upload['new'] as $t) {
    $t->flush()->unlockUpload();
    $tracker->addTorrent($t);
    $bonusTotal += $uploadReward->reward($t);
    $folderCheck[] = $t->path();
}
if (!$Viewer->disableBonusPoints()) {
    new User\Bonus($Viewer)->addPoints($bonusTotal);
}

new Manager\NotificationTicket()->create($torrent);
$tgroup->refresh();

if ($Viewer->option('AutoSubscribe')) {
    new User\Subscription($Viewer)->subscribeComments('torrents', $GroupID);
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
    $Cache->delete_value('user_recent_up_' . $Viewer->id);
}

$folderClash = 0;
if ($isMusicUpload) {
    foreach ($folderCheck as $foldername) {
        // This also has the nice side effect of warming the cache immediately
        $folderClash += max(0, count($torMan->findAllByFoldername($foldername)) - 1);
    }
}

if (defined('AJAX')) {
    $Response = [
        'groupId'       => $GroupID,
        'newgroup'      => $IsNewGroup,
        'torrentId'     => $TorrentID,
        'private'       => !$PublicTorrent,
        'source'        => !$UnsourcedTorrent,
        'extraTorrents' => [],
        'warnings'      => [],
    ];
    foreach ($upload['extra'] as $xInfo) {
        $Response['extraTorrents'][] = [
            'torrentId' => $xInfo['TorrentID'],
            'private'   => $xInfo['Private'],
            'source'    => $xInfo['HasSource'],
        ];
    }

    if (isset($RequestID)) {
        define('NO_AJAX_ERROR', true);
        $_REQUEST['torrentid'] = $TorrentID;
        $FillResponse = include_once __DIR__ . '/../requests/fill_handle.php';
        if (!isset($FillResponse['requestId'])) {
            $FillResponse = [
                'status' => 400,
                'error' => $FillResponse,
            ];
        }
        $Response['fillRequest'] = $FillResponse;
    }

    if ($PublicTorrent) {
        $Response['warnings'][] = trim($Twig->render('upload/warnings/public.twig'));
    }
    if ($UnsourcedTorrent) {
        $Response['warnings'][] = trim($Twig->render('upload/warnings/unsourced.twig'));
    }
    if ($PublicTorrent || $UnsourcedTorrent) {
        $Response['warnings'][] = trim($Twig->render('upload/warnings/redownload.twig', ['torrent' => $torrent]));
    }
    if ($folderClash > 0) {
        $Response['warnings'][] = trim($Twig->render('upload/warnings/folderclash.twig', ['clash' => $folderClash]));
    }
    json_print('success', $Response);
    exit;
}

// all uploads should go through ajax.php, but we keep this for backwards compatibility with old scripts

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
