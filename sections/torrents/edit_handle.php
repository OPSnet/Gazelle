<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Debug $Debug */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;
use Gazelle\Enum\TorrentFlag;
use OrpheusNET\Logchecker\Logchecker;

authorize();

$torMan = new Manager\Torrent();
$torrent = $torMan->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    Error404::error();
}
$uploaderId = $torrent->uploaderId();

if ($Viewer->id != $uploaderId && !$Viewer->permitted('torrents_edit')) {
    Error403::error("You are not allowed to edit this torrent");
}

//******************************************************************************//
//--------------- Set $Properties array ----------------------------------------//
// This is used if the form doesn't validate, and when the time comes to enter  //
// it into the database.                                                        //
//******************************************************************************//

$Properties = [
    'Description' => trim($_POST['release_desc'] ?? ''),
    'Name'        => trim($_POST['title'] ?? ''),
    'Encoding'    => $_POST['bitrate'] ?? null,
    'Format'      => $_POST['format'] ?? null,
    'Media'       => $_POST['media'] ?? '',
    'HasLog'      => isset($_POST['flac_log']),
    'HasCue'      => isset($_POST['flac_cue']),
    'Scene'       => isset($_POST['scene']),
];
if (isset($_POST['album_desc'])) {
    $Properties['GroupDescription'] = trim($_POST['album_desc']);
}
$Properties['UnknownRelease']          = isset($_POST['unknown']);
$Properties['RemasterYear']            = (int)($_POST['remaster_year'] ?? 0);
$Properties['RemasterTitle']           = trim($_POST['remaster_title'] ?? '');
$Properties['RemasterRecordLabel']     = trim($_POST['remaster_record_label'] ?? '');
$Properties['RemasterCatalogueNumber'] = trim($_POST['remaster_catalogue_number'] ?? '');
foreach (TorrentFlag::cases() as $flag) {
    $Properties[$flag->value] = isset($_POST[$flag->value]);
}

//******************************************************************************//
//--------------- Validate data in edit form -----------------------------------//

if (!$Viewer->permitted('edit_unknowns')) {
    if (
        $Viewer->id !== $uploaderId
        && $Properties['UnknownRelease']
        && $torrent->remasterYear() !== null
    ) {
        Error400::error("You may not set someone else's upload to unknown release.");
    }
}

$Validate = new Util\Validator();
switch ($torrent->group()->categoryName()) {
    case 'Music':
        if (!is_null($Properties['RemasterYear'])) {
            $future = (int)date('Y') + 1;
            if ($Properties['RemasterYear'] > $future && !$Viewer->permitted('users_mod')) {
                Error400::error(
                    "You may not specify a remaster year that far in the future. Instead, set it to $future and report the upload afterwards to have the year set as appropriate by staff."
                );
            }
        }
        if (!$Properties['UnknownRelease'] && $Properties['RemasterYear'] < 1982 && $Properties['Media'] == 'CD') {
            Error400::error(
                'You have selected a year for an album that predates the medium you say it was created on.'
            );
        }
        if ($Properties['RemasterTitle'] == 'Original Release') {
            Error400::error('"Original Release" is not a valid remaster title.');
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

        if ($Properties['UnknownRelease']) {
            $Properties['RemasterYear']            = null;
            $Properties['RemasterTitle']           = '';
            $Properties['RemasterRecordLabel']     = '';
            $Properties['RemasterCatalogueNumber'] = '';
        } else {
            $Validate->setField('remaster_year', true, 'number', 'Year of remaster/re-issue must be entered.');
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

    $banned = Util\ImageProxy::badHost($Properties['Image']);
    if ($banned) {
        $Err = "Please rehost images from $banned elsewhere.";
    }
}

if ($Err) {
    Error400::error($Err);
}

$propertyMap = [
    'Media'                   => 'media',
    'Format'                  => 'format',
    'Encoding'                => 'encoding',
    'Scene'                   => 'isScene',
    'Description'             => 'description',
    'RemasterYear'            => 'remasterYear',
    'RemasterTitle'           => 'remasterTitle',
    'RemasterRecordLabel'     => 'remasterRecordLabel',
    'RemasterCatalogueNumber' => 'remasterCatalogueNumber',
];

$change = [];
foreach ($propertyMap as $field => $method) {
    if (!method_exists($torrent, $method)) {
        $Debug->saveCase("bad method $method in torrent edit id={$torrent->id}");
        Error400::error('Cannot proceed with torrent edit');
    }
    $value = $torrent->$method();
    if (isset($Properties[$field])) {
        if (($value ?? '') !== ($Properties[$field] ?? '')) {
            if (is_bool($Properties[$field])) {
                $change[] = sprintf("$field %s → %s",
                    $value ? 'true' : 'false',
                    $Properties[$field] ? 'true' : 'false'
                );
            } else {
                $change[] = "$field $value → {$Properties[$field]}";
            }
            if ($field === 'Scene') {
                $torrent->setField($field, $Properties[$field] ? '1' : '0');
            } else {
                $torrent->setField($field, $Properties[$field]);
            }
        }
    }
}

$hasCue = false;
$hasLog = false;
foreach ($torrent->fileList() as $file) {
    if ($file['ext'] === '.cue') {
        $hasCue = true;
        if ($hasLog) {
            break; // no further changes possible
        }
    } elseif (
        $file['ext'] === '.log'
        && !in_array($file['ext'], IGNORE_AUDIO_LOGFILE)
    ) {
        $hasLog = true;
        if ($hasCue) {
            break;
        }
    }
}
if (
    $Properties['Encoding'] === 'Lossless'
    && $torrent->encoding() !== 'Lossless'
    && $hasCue
    && !$torrent->hasCue()
) {
    $torrent->setField('HasCue', '1');
    $change[] = "HasCue false → true";
} elseif (
    $Properties['Encoding'] !== 'Lossless'
    && $torrent->hasCue()
) {
    $torrent->setField('HasCue', '0');
    $change[] = "HasCue true → false";
} elseif (
    $Viewer->permitted('users_mod')
    && $torrent->hasCue() != $Properties['HasCue']
) {
    $torrent->setField('HasCue', $Properties['HasCue'] ? '1' : '0');
    $change[] = sprintf("HasCue %s → %s",
        $torrent->hasCue() ? 'true' : 'false',
        $Properties['HasCue'] ? 'true' : 'false'
    );
}
if (
    (
        ($Properties['Encoding'] === 'Lossless' && $torrent->encoding() !== 'Lossless')
        || ($Properties['Media'] === 'CD' && $torrent->media() !== 'CD')
    )
    && $hasLog
    && !$torrent->hasLog()
) {
    $torrent->setField('HasLog', '1');
    $change[] = "HasLog false → true";
} elseif (
    ($Properties['Encoding'] !== 'Lossless' || $Properties['Media'] !== 'CD')
    && $torrent->hasLog()
) {
    $torrent->setField('HasLog', '0');
    $change[] = "HasLog true → false";
} elseif (
    $Viewer->permitted('users_mod')
    && $torrent->hasLog() != $Properties['HasLog']
) {
    $torrent->setField('HasLog', $Properties['HasLog'] ? '1' : '0');
    $change[] = sprintf("HasLog %s → %s",
        $torrent->hasLog() ? 'true' : 'false',
        $Properties['HasLog'] ? 'true' : 'false'
    );
}

//******************************************************************************//
//--------------- Start database stuff -----------------------------------------//

$db = DB::DB();
$db->begin_transaction(); // It's all or nothing

if (isset($_FILES['logfiles'])) {
    $logfileSummary = new LogfileSummary($_FILES['logfiles'], $torrent->logfileHashList());
    if ($logfileSummary->total()) {
        $torrentLogManager = new Manager\TorrentLog();
        $checkerVersion = Logchecker::getLogcheckerVersion();
        foreach ($logfileSummary->all() as $logfile) {
            $torrentLogManager->create($torrent, $logfile, $checkerVersion);
        }
        $torrent->modifyLogscore();
    }
}

foreach (TorrentFlag::cases() as $flag) {
    if ($flag->permission() && !$Viewer->permitted($flag->permission())) {
        continue;
    }
    $exists = $torrent->hasFlag($flag);
    if (!$exists && $Properties[$flag->value]) {
        $torrent->addFlag($flag, $Viewer);
    } elseif ($exists && !$Properties[$flag->value]) {
        $torrent->removeFlag($flag, $Viewer);
    }
}

if ($Viewer->permitted('torrents_freeleech')) {
    $reason    = $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::Normal->value);
    $leechType = $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Normal->value);
    if ($leechType != $torrent->leechType() || $reason != $torrent->leechReason()) {
        $torMan->setListFreeleech(
            tracker:   new Tracker(),
            idList:    [$torrent->id],
            leechType: $leechType,
            reason:    $reason,
            user:      $Viewer,
        );
    }
}

$torrent->modify();
$torrent->group()->refresh();

$db->commit();

$changeLog = shortenString(implode(', ', $change), 300);
$torrent->logger()->torrent($torrent, $Viewer, $changeLog)
    ->general(
        "Torrent {$torrent->id} ({$torrent->group()->name()}) in group {$torrent->groupId()} was edited by {$Viewer->username()} ($changeLog)"
    );

header("Location: " . $torrent->location());
