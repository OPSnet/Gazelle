<?php

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;
use Gazelle\Enum\TorrentFlag;
use OrpheusNET\Logchecker\Logchecker;

authorize();

$torMan = new Gazelle\Manager\Torrent();
$torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}
$Remastered   = $torrent->isRemastered();
$RemasterYear = $torrent->remasterYear();
$TorrentID    = $torrent->id();
$UserID       = $torrent->uploaderId();

if ($Viewer->id() != $UserID && !$Viewer->permitted('torrents_edit')) {
    error(403);
}

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //
//******************************************************************************//

$Properties = [
    'Name'                => trim($_POST['title'] ?? ''),
    'Format'              => $_POST['format'],
    'Media'               => $_POST['media'] ?? '',
    'Encoding'            => $_POST['bitrate'],
    'TorrentDescription'  => trim($_POST['release_desc'] ?? ''),
    'Scene'               => isset($_POST['scene']) ? '1' : '0',
    'HasLog'              => isset($_POST['flac_log']) ? '1' : '0',
    'HasCue'              => isset($_POST['flac_cue']) ? '1' : '0',
    'Remastered'          => isset($_POST['remaster']),
    'BadTags'             => isset($_POST['bad_tags']),
    'BadFolders'          => isset($_POST['bad_folders']),
    'BadFiles'            => isset($_POST['bad_files']),
    'Lineage'             => isset($_POST['missing_lineage']),
    'CassetteApproved'    => isset($_POST['cassette_approved']),
    'LossymasterApproved' => isset($_POST['lossymaster_approved']),
    'LossywebApproved'    => isset($_POST['lossyweb_approved']),
];
if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = trim($_POST['album_desc']);
}
if ($Properties['Remastered']) {
    $Properties['UnknownRelease']          = isset($_POST['unknown']);
    $Properties['RemasterYear']            = isset($_POST['remaster_year']) ? (int)$_POST['remaster_year'] : null;
    $Properties['RemasterTitle']           = trim($_POST['remaster_title'] ?? '');
    $Properties['RemasterRecordLabel']     = trim($_POST['remaster_record_label'] ?? '');
    $Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number'] ?? '');
} else {
    $Properties['UnknownRelease']          = false;
    $Properties['RemasterYear']            = null;
    $Properties['RemasterTitle']           = '';
    $Properties['RemasterRecordLabel']     = '';
    $Properties['RemasterCatalogueNumber'] = '';
}

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

if (!$Viewer->permitted('edit_unknowns')) {
    if ($Remastered && !$RemasterYear) {
        error("You must supply a remaster year for a remastered release");
    }
    if ($Properties['UnknownRelease'] && !($Remastered && !$RemasterYear)) { /** @phpstan-ignore-line *//* wtf is this logic */
        if ($Viewer->id() != $UserID) {
            error("You cannot set a release to be Unknown");
        }
    }
    if ($Viewer->id() !== $UserID && $Properties['Remastered'] && !$Properties['RemasterYear']) {
        $Err = "You may not set someone else's upload to unknown release.";
    }
}

$Validate = new Gazelle\Util\Validator();
$Validate->setField('type', true, 'number', 'Not a valid category.', ['range' => [1, count(CATEGORY)]]);
switch (CATEGORY[(int)($_POST['type'] ?? 0) - 1]) {
    case 'Music':
        if ($Properties['Remastered'] && !$Properties['UnknownRelease'] && $Properties['RemasterYear'] < 1982 && $Properties['Media'] == 'CD') {
            error('You have selected a year for an album that predates the medium you say it was created on.');
        }
        if ($Properties['RemasterTitle'] == 'Original Release') {
            error('"Original Release" is not a valid remaster title.');
        }

        $Validate->setFields([
            ['format', true, 'inarray', 'Not a valid format.', ['inarray' => FORMAT]],
            ['bitrate', true, 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]],
            ['media', true, 'inarray', 'Not a valid media.', ['inarray' => MEDIA]],
            ['release_desc', false, 'string', 'Invalid release description.', ['range' => [0, 1_000_000]]],
            ['remaster_title', false, 'string', 'Remaster title must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_record_label', false, 'string', 'Remaster record label must be between 1 and 80 characters.', ['range' => [1, 80]]],
            ['remaster_catalogue_number', false, 'string', 'Remaster catalogue number must be between 1 and 80 characters.', ['range' => [1, 80]]],
        ]);

        if ($Properties['Remastered'] && !$Properties['UnknownRelease']) {
            $Validate->setField('remaster_year', true, 'number', 'Year of remaster/re-issue must be entered.');
        } else {
            $Validate->setField('remaster_year', false, 'number', 'Invalid remaster year.');
        }

        if ($Properties['Encoding'] !== 'Other') {
            $Validate->setField('bitrate', true, 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]);
        } else {
            // Handle 'other' bitrates
            $Validate->setField('other_bitrate', true, 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');
        }
        break;

    case 'Audiobooks':
    case 'Comedy':
        $Validate->setFields([
            ['year', true, 'number', 'The year of the release must be entered.'],
            ['format', true, 'inarray', 'Not a valid format.', ['inarray' => FORMAT]],
            ['bitrate', true, 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]],
            ['release_desc', false, 'string', 'The release description has a minimum length of 10 characters.', ['rang' => [10, 1_000_000]]],
        ]);
        // Handle 'other' bitrates
        if ($Properties['Encoding'] !== 'Other') {
            $Validate->setField('bitrate', true, 'inarray', 'You must choose a bitrate.', ['inarray' => ENCODING]);
        } else {
            $Validate->setField('other_bitrate', true, 'text', 'You must enter the other bitrate (max length: 9 characters).', ['maxlength' => 9]);
            $Properties['Encoding'] = trim($_POST['other_bitrate']) . (!empty($_POST['vbr']) ? ' (VBR)' : '');
        }
        break;

    default:
        break;
}

$Err = $Validate->validate($_POST) ? false : $Validate->errorMessage();

if (!$Err && isset($Properties['Image'])) { /** @phpstan-ignore-line */
    // Strip out Amazon's padding
    if (preg_match('/(http:\/\/ecx.images-amazon.com\/images\/.+)(\._.*_\.jpg)/i', $Properties['Image'], $match)) {
        $Properties['Image'] = $match[1] . '.jpg';
    }

    if (!preg_match(IMAGE_REGEXP, $Properties['Image'])) {
        $Err = display_str($Properties['Image']) . " does not look like a valid image url";
    }

    $banned = (new Gazelle\Util\ImageProxy($Viewer))->badHost($Properties['Image']);
    if ($banned) {
        $Err = "Please rehost images from $banned elsewhere.";
    }
}

if ($Err) { // Show the upload form, with the data the user entered
    error($Err);
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$db = Gazelle\DB::DB();
$current = $db->rowAssoc("
    SELECT GroupID, Media, Format, Encoding, Scene, Description AS TorrentDescription,
        RemasterYear, Remastered, RemasterTitle, RemasterRecordLabel, RemasterCatalogueNumber
    FROM torrents
    WHERE ID = ?
    ", $TorrentID
);
$current['Remastered'] = ($current['Remastered'] === '1');
$change = [];
foreach ($current as $key => $value) {
    if ($key == 'GroupID') {
        // Not needed here, used below
        continue;
    }
    if (isset($Properties[$key]) && $value !== $Properties[$key]) {
        if (is_bool($Properties[$key])) {
            $change[] = sprintf("%s %s \xE2\x86\x92 %s", $key, $value ? 'true' : 'false', $Properties[$key] ? 'true' : 'false');
        } else {
            $change[] = sprintf("%s %s \xE2\x86\x92 %s", $key, $value, $Properties[$key]);
        }
    }
}

$logfileSummary = new Gazelle\LogfileSummary($_FILES['logfiles']);

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$db->begin_transaction(); // It's all or nothing

if ($logfileSummary->total()) {
    $torrentLogManager = new Gazelle\Manager\TorrentLog(new Gazelle\File\RipLog(), new Gazelle\File\RipLogHTML());
    $checkerVersion = Logchecker::getLogcheckerVersion();
    foreach ($logfileSummary->all() as $logfile) {
        $torrentLogManager->create($torrent, $logfile, $checkerVersion);
    }
    $torrent->modifyLogscore();
}

// Update info for the torrent
$set = [
    'Description = ?', 'Media = ?', 'Format = ?', 'Encoding = ?', 'Scene = ?',
    'Remastered = ?', 'RemasterYear = ?', 'RemasterTitle = ?',
    'RemasterRecordLabel = ?', 'RemasterCatalogueNumber = ?',
];
$args = [
    $Properties['TorrentDescription'], $Properties['Media'], $Properties['Format'], $Properties['Encoding'], $Properties['Scene'],
    $Properties['Remastered'] ? '1' : '0', $Properties['RemasterYear'], $Properties['RemasterTitle'],
    $Properties['RemasterRecordLabel'], $Properties['RemasterCatalogueNumber'],
];

if ($Viewer->permitted('users_mod')) {
    $set = array_merge($set, ['HasLog = ?', 'HasCue = ?']);
    if ($Properties['Format'] == 'FLAC' && $Properties['Media'] == 'CD') {
        $args = array_merge($args, [$Properties['HasLog'], $Properties['HasCue']]);
    } else {
        $args = array_merge($args, ['0', '0']);
    }

    foreach (
        [
            (object)['flag' => TorrentFlag::badFile,     'property' => 'BadFiles'],
            (object)['flag' => TorrentFlag::badFolder,   'property' => 'BadFolders'],
            (object)['flag' => TorrentFlag::badTag,      'property' => 'BadTags'],
            (object)['flag' => TorrentFlag::cassette,    'property' => 'CassetteApproved'],
            (object)['flag' => TorrentFlag::lossyMaster, 'property' => 'LossymasterApproved'],
            (object)['flag' => TorrentFlag::lossyWeb,    'property' => 'LossywebApproved'],
        ] as $f
    ) {
        $exists = $torrent->hasFlag($f->flag);
        if (!$exists && $Properties[$f->property]) {
            $change[] = "{$f->flag->label()} checked";
            $torrent->addFlag($f->flag, $Viewer);
        } elseif ($exists && !$Properties[$f->property]) {
            $change[] = "{$f->flag->label()} cleared";
            $torrent->removeFlag($f->flag);
        }
    }
}
if ($Viewer->permitted('site_edit_lineage')) {
    $lineage = TorrentFlag::noLineage;
    $exists = $torrent->hasFlag($lineage);
    if (!$exists && $Properties['Lineage']) {
        $change[] = "{$lineage->label()} checked";
        $torrent->addFlag($lineage, $Viewer);
    } elseif ($exists && !$Properties['Lineage']) {
        $change[] = "{$lineage->label()} cleared";
        $torrent->removeFlag($lineage);
    }
}

$args[] = $TorrentID;
$db->prepared_query("
    UPDATE torrents SET
    " . implode(', ', $set) . "
    WHERE ID = ?
    ", ...$args
);

if ($Viewer->permitted('torrents_freeleech')) {
    $reason    = $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::Normal->value);
    $leechType = $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Normal->value);
    if ($leechType != $torrent->leechType() || $reason != $torrent->leechReason()) {
        $torMan->setListFreeleech(
            tracker:   new Gazelle\Tracker(),
            idList:    [$torrent->id()],
            leechType: $leechType,
            reason:    $reason,
            user:      $Viewer,
        );
    }
}
$db->commit();

$torrent->group()->refresh();

$changeLog = implode(', ', $change);
(new Gazelle\Log())->torrent($torrent, $Viewer, $changeLog)
    ->general("Torrent $TorrentID ({$torrent->group()->name()}) in group {$current['GroupID']} was edited by "
        . $Viewer->username() . " ($changeLog)");

$torrent->flush();
header("Location: " . $torrent->location());
