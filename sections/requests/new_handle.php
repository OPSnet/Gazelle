<?php

authorize();

if (!$Viewer->permitted('site_submit_requests') || $Viewer->uploadedSize() < 250 * 1024 * 1024) {
    error(403);
}

$categoryName = $_POST['type'] ?? '';
$categoryId   = array_search($categoryName, CATEGORY);
if ($categoryId === false) {
    error('request category corrupt');
}
$categoryId += 1;

$artistRole   = null;
$description  = null;
$encoding     = null;
$format       = null;
$image        = null;
$logCue       = null;
$media        = null;
$releaseType  = null;
$tags         = null;
$tgroup       = null;
$tgroup       = null;
$title        = null;
$year         = null;

$encoding = new Gazelle\Request\Encoding(
    isset($_POST['all_bitrates']),
    array_trim_prefix('bitrate_', $_POST['bitrates'] ?? [])
);
$format = new Gazelle\Request\Format(
    isset($_POST['all_formats']),
    array_trim_prefix('format_', $_POST['formats'] ?? [])
);
$media = new Gazelle\Request\Media(
    isset($_POST['all_media']),
    array_trim_prefix('media_', $_POST['media'] ?? [])
);
$releaseType     = (int)$_POST['releasetype'];
$description     = trim($_POST['description'] ?? '');
$title           = trim($_POST['title'] ?? '');
$tags            = trim($_POST['tags'] ?? '');
$image           = trim($_POST['image'] ?? '');
$catalogueNumber = trim($_POST['cataloguenumber'] ?? '');
$recordLabel     = trim($_POST['recordlabel'] ?? '');
$oclc            = trim($_POST['oclc'] ?? '');
$year            = (int)$_POST['year'];

$amount  = (int)$_POST['amount_box'];
$unitGiB = ($_POST['unit'] ?? 'mb') == 'gb';
$scale   = 1024 ** ($unitGiB ? 3 : 2);

while (true) { // break early on error
    if ($categoryName !== 'Music') {
        $artistRole = [];
        $logCue = new Gazelle\Request\LogCue();
    } else {
        $logCue = $format->exists('FLAC') && $media->exists('CD')
            ? new Gazelle\Request\LogCue(
                isset($_POST['needcksum']),
                isset($_POST['needcue']),
                isset($_POST['needlog']),
                (int)($_POST['minlogscore'] ?? 0)
            )
            : new Gazelle\Request\LogCue();

        if (empty($_POST['artists'])) {
            $error = 'You did not enter any artists.';
            break;
        }
        $artistList = $_POST['artists'];
        $roleList   = $_POST['importance'];

        $main = 0;
        $seen = [];
        $artistRole = [];
        for ($i = 0, $il = count($artistList); $i < $il; $i++) {
            $name = trim($artistList[$i]);
            if ($name == '' || in_array($name, $seen)) {
                continue;
            }
            $seen[] = $name;
            $role = $roleList[$i];
            if (!isset($artistRole[$role])) {
                $artistRole[$role] = [];
            }
            $artistRole[$role][] = $name;
            if (in_array($role, [ARTIST_ARRANGER, ARTIST_COMPOSER, ARTIST_CONDUCTOR, ARTIST_DJ, ARTIST_MAIN])) {
                $main++;
            }
        }
        if ($main < 1) {
            $error = 'Please enter at least one main artist, conductor, arranger, composer, or DJ.';
            break;
        }

        if (!$logCue->isValid()) {
            $error = 'You have entered a minimum log score that is not between 0 and 100 inclusive.';
            break;
        }

        if (!(new Gazelle\ReleaseType())->findNameById($releaseType)) {
            $error = 'Please pick a release type';
            break;
        }

        if (!$format->isValid()) {
            $error = 'You must require at least one valid format';
            break;
        }

        if (!$encoding->isValid()) {
            $error = 'You must require at least one valid encoding';
            break;
        }

        if ($format->exists('FLAC') && !($encoding->exists('Lossless') || $encoding->exists('24bit Lossless'))) {
            $error = 'You selected FLAC as a format but no encoding to fill it (Lossless and/or 24bit Lossless)';
            break;
        }

        if (!$media->isValid()) {
            $error = 'You must require at least one valid media';
            break;
        }
    }

    // GroupID
    if (!isset($_POST['groupid'])) {
        $tgroup = null;
    } else {
        $GroupID = preg_match(TGROUP_REGEXP, trim($_POST['groupid']), $match)
            ? (int)$match['id']
            : (int)$_POST['groupid'];
        if ($GroupID > 0) {
            $tgroup = (new Gazelle\Manager\TGroup())->findById($GroupID);
            if (is_null($tgroup)) {
                $error = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
                break;
            }
        }
    }

    $validator = new Gazelle\Util\Validator();
    $validator->setFields([
        ['description', true,  'string', 'You forgot to enter a description.', ['maxlength' => 32000]],
        ['image',       false, 'image',  ''],
        ['title',       true,  'string', 'You forgot to enter the title!', ['maxlength' => 255]],
        ['tags',        true,  'string', 'You forgot to enter any tags!', ['maxlength' => 255]],
    ]);
    if ($categoryName == 'Music') {
        $validator->setField('year', true,  'number', 'The year of the release must be entered.', ['maxlength' => date('Y') + 2]);
    }

    if (!$validator->validate($_POST)) {
        $error = $validator->errorMessage();
        break;
    }

    if ($amount * $scale < REQUEST_MIN * 1024 * 1024) {
        $error = 'Minimum bounty is ' . REQUEST_MIN . ' MiB.';
        break;
    }

    // in case the Javascript check is ignored
    if ($amount * $scale > $Viewer->uploadedSize()) {
        $units = $unitGiB ? 'GiB' : 'MiB';
        $error = "You do not have enough buffer to offer a bounty of $amount $units";
    }

    break; // everything validated!
}

if (isset($error)) {
    if ($year == 0) {
        $year = null;
    }
    require_once('new.php');
    exit;
}

$request = (new Gazelle\Manager\Request())->create(
    user:            $Viewer,
    bounty:          (int)($amount * $scale),
    categoryId:      $categoryId,
    year:            $year,
    title:           $title,
    image:           $image,
    description:     $description,
    recordLabel:     trim($_POST['recordlabel'] ?? ''),
    catalogueNumber: trim($_POST['cataloguenumber'] ?? ''),
    releaseType:     $releaseType,
    encodingList:    $encoding->dbValue(),
    formatList:      $format->dbValue(),
    mediaList:       $media->dbValue(),
    logCue:          $logCue->dbValue(),
    checksum:        $logCue->needLogChecksum(),
    oclc:            trim($_POST['oclc'] ?? ''),
    groupId:         $tgroup?->id(),
);
if ($categoryName == 'Music') {
    $request->artistRole()->set($artistRole, new Gazelle\Manager\Artist());
}
$request->setTagList(array_unique(array_map('trim', explode(',', $tags))), $Viewer, new Gazelle\Manager\Tag());
$tgroup?->flush();

if ($Viewer->option('AutoSubscribe')) {
    (new Gazelle\User\Subscription($Viewer))->subscribeComments('requests', $request->id());
}

Gazelle\Util\Irc::sendMessage(
    IRC_CHAN_REQUEST,
    "{$request->text()} – {$request->publicLocation()} – " . implode(' ', $request->tagNameList())
);

header("Location: {$request->location()}");
