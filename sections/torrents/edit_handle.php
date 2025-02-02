<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Debug $Debug */

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
    'Format'              => $_POST['format'] ?? null,
    'Media'               => $_POST['media'] ?? '',
    'Encoding'            => $_POST['bitrate'] ?? null,
    'Description'         => trim($_POST['release_desc'] ?? ''),
    'Scene'               => isset($_POST['scene']),
    'HasLog'              => isset($_POST['flac_log']),
    'HasCue'              => isset($_POST['flac_cue']),
    'Remastered'          => isset($_POST['remaster']),
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
foreach (TorrentFlag::cases() as $flag) {
    $Properties[$flag->value] = isset($_POST[$flag->value]);
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

if ($Err) {
    error($Err);
}

$propertyMap = [
    'Media'                   => 'media',
    'Format'                  => 'format',
    'Encoding'                => 'encoding',
    'Scene'                   => 'isScene',
    'Description'             => 'description',
    'RemasterYear'            => 'remasterYear',
    'Remastered'              => 'isRemastered',
    'RemasterTitle'           => 'remasterTitle',
    'RemasterRecordLabel'     => 'remasterRecordLabel',
    'RemasterCatalogueNumber' => 'remasterCatalogueNumber',
];

$change = [];
foreach ($propertyMap as $field => $method) {
    if (!method_exists($torrent, $method)) {
        $Debug->saveCase("bad method $method in torrent edit id={$torrent->id()}");
        error('Cannot proceed with torrent edit');
    }
    $value = $torrent->$method();
    if (isset($Properties[$field])) {
        // soft inequality, to match null versus ''
        if ($value != $Properties[$field]) {
            if (is_bool($Properties[$field])) {
                $change[] = sprintf("$field %s → %s",
                    $value ? 'true' : 'false',
                    $Properties[$field] ? 'true' : 'false'
                );
            } else {
                $change[] = "$field $value → {$Properties[$field]}";
            }
            if (in_array($field, ['Remastered', 'Scene'])) {
                $torrent->setField($field, $Properties[$field] ? '1' : '0');
            } else {
                $torrent->setField($field, $Properties[$field]);
            }
        }
    }
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$db = Gazelle\DB::DB();
$db->begin_transaction(); // It's all or nothing

if (isset($_FILES['logfiles'])) {
    $logfileSummary = new Gazelle\LogfileSummary($_FILES['logfiles']);
    if ($logfileSummary->total()) {
        $torrentLogManager = new Gazelle\Manager\TorrentLog(new Gazelle\File\RipLog(), new Gazelle\File\RipLogHTML());
        $checkerVersion = Logchecker::getLogcheckerVersion();
        foreach ($logfileSummary->all() as $logfile) {
            $torrentLogManager->create($torrent, $logfile, $checkerVersion);
        }
        $torrent->modifyLogscore();
    }
}

if ($Viewer->permitted('users_mod')) {
    if ($Properties['Format'] == 'FLAC' && $Properties['Media'] == 'CD') {
        if ($torrent->hasLog() != $Properties['HasLog']) {
            $torrent->setField('HasLog', $Properties['HasLog'] ? '1' : '0');
            $change[] = sprintf("HasLog %s → %s",
                $torrent->hasLog() ? 'true' : 'false',
                $Properties['HasLog'] ? 'true' : 'false'
            );
        }
        if ($torrent->hasCue() != $Properties['HasCue']) {
            $torrent->setField('HasCue', $Properties['HasCue'] ? '1' : '0');
            $change[] = sprintf("HasCue %s → %s",
                $torrent->hasCue() ? 'true' : 'false',
                $Properties['HasCue'] ? 'true' : 'false'
            );
        }
    } else {
        if ($torrent->hasLog()) {
            $torrent->setField('HasLog', '0');
            $change[] = "HasLog cleared";
        }
        if ($torrent->hasCue()) {
            $torrent->setField('HasCue', '0');
            $change[] = "HasCue cleared";
        }
    }

    foreach (TorrentFlag::cases() as $flag) {
        if ($flag->permission() && !$Viewer->permitted($flag->permission())) {
            continue;
        }
        $exists = $torrent->hasFlag($flag);
        if (!$exists && $Properties[$flag->value]) {
            $change[] = "{$flag->label()} checked";
            $torrent->addFlag($flag, $Viewer);
        } elseif ($exists && !$Properties[$flag->value]) {
            $change[] = "{$flag->label()} cleared";
            $torrent->removeFlag($flag);
        }
    }
}

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

$torrent->modify();
$torrent->group()->refresh();
$torrent->flush();

$db->commit();

$changeLog = shortenString(implode(', ', $change), 300);
(new Gazelle\Log())->torrent($torrent, $Viewer, $changeLog)
    ->general("Torrent $TorrentID ({$torrent->group()->name()}) in group {$torrent->groupId()} was edited by "
        . $Viewer->username() . " ($changeLog)");

header("Location: " . $torrent->location());
