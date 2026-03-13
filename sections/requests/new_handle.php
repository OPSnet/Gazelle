<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('site_submit_requests') || $Viewer->uploadedSize() < 250 * 1024 * 1024) {
    Error403::error();
}

$categoryName = $_POST['type'] ?? '';
$categoryId   = array_search($categoryName, CATEGORY);
if ($categoryId === false) {
    Error400::error('request category corrupt');
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
$title        = null;
$year         = null;

$encoding = new Request\Encoding(
    isset($_POST['all_bitrates']),
    array_map(fn($n) => ENCODING[$n], $_POST['bitrates'] ?? []),
);
$format = new Request\Format(
    isset($_POST['all_formats']),
    array_map(fn($n) => FORMAT[$n], $_POST['formats'] ?? []),
);
$media = new Request\Media(
    isset($_POST['all_media']),
    array_map(fn($n) => MEDIA[$n], $_POST['media'] ?? []),
);
$releaseType     = (int)$_POST['releasetype'];
$description     = trim($_POST['description'] ?? '');
$title           = trim($_POST['title'] ?? '');
$tags            = trim($_POST['tags'] ?? '');
$image           = trim($_POST['image'] ?? '');
$catalogueNumber = trim($_POST['cataloguenumber'] ?? '');
$recordLabel     = trim($_POST['recordlabel'] ?? '');
$year            = (int)$_POST['year'];
$amount          = (int)$_POST['amount'];

while (true) { // break early on error
    if ($categoryName !== 'Music') {
        $artistRole = [];
        $logCue = new Request\LogCue();
    } else {
        $logCue = $format->exists('FLAC') && $media->exists('CD')
            ? new Request\LogCue(
                isset($_POST['needcksum']),
                isset($_POST['needcue']),
                isset($_POST['needlog']),
                (int)($_POST['minlogscore'] ?? 0)
            )
            : new Request\LogCue();

        if ($_POST['artists'] === []) {
            $error = 'You did not enter any artists.';
            break;
        }
        $artistMan  = new Manager\Artist();
        $artistList = $_POST['artists'];
        $roleList   = array_map('intval', $_POST['importance']);
        $artistRole = [];
        $seen       = [];
        $main       = 0;
        for ($i = 0, $il = count($artistList); $i < $il; $i++) {
            $name = trim($artistList[$i]);
            if ($name == '' || in_array($name, $seen)) {
                continue;
            }
            $seen[] = $name;
            $roleId = $roleList[$i];
            if (!$artistMan->roleExists(CATEGORY_MUSIC, $roleId)) {
                // ignore bogus artist role
                continue;
            }
            if (!isset($artistRole[$roleId])) {
                $artistRole[$roleId] = [];
            }
            $artistRole[$roleId][] = $name;
            if (in_array($roleId, [ARTIST_ARRANGER, ARTIST_COMPOSER, ARTIST_CONDUCTOR, ARTIST_DJ, ARTIST_MAIN])) {
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

        if (!new ReleaseType()->findNameById($releaseType)) {
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
            $tgroup = new Manager\TGroup()->findById($GroupID);
            if (is_null($tgroup)) {
                $error = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
                break;
            }
        }
    }

    $validator = new Util\Validator();
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

    if ($amount < REQUEST_MIN * 1024 * 1024) {
        $error = 'Minimum bounty is ' . REQUEST_MIN . ' MiB.';
        break;
    }

    // in case the Javascript check is ignored
    if ($amount > $Viewer->uploadedSize()) {
        $units = ($_POST['unit'] ?? 'mb') == 'gb' ? 'GiB' : 'MiB';
        $error = "You do not have enough buffer to offer a bounty of {$_POST['amount_box']} $units";
    }

    break; // everything validated!
}

if (isset($error)) {
    if ($year == 0) {
        $year = null;
    }
    require __DIR__ . '/new.php';
    exit;
}

$request = new Manager\Request()->create(
    user:            $Viewer,
    bounty:          $amount,
    categoryId:      $categoryId,
    year:            $year,
    title:           $title,
    image:           $image,
    description:     $description,
    recordLabel:     trim($_POST['recordlabel'] ?? ''),
    catalogueNumber: trim($_POST['cataloguenumber'] ?? ''),
    releaseType:     $releaseType,
    encoding:        $encoding,
    format:          $format,
    media:           $media,
    logCue:          $logCue,
    groupId:         $tgroup?->id,
);
if ($categoryName == 'Music') {
    $request->artistRole()->set($artistRole, $Viewer);
}
new Manager\Tag()->replaceTagList(
    $request,
    array_map('trim', explode(',', $tags)),
    $Viewer
);
$tgroup?->flush();

if ($Viewer->option('AutoSubscribe')) {
    new User\Subscription($Viewer)->subscribeComments('requests', $request->id);
}

Util\Irc::sendMessage(
    IRC_CHAN_REQUEST,
    "{$request->text()} – {$request->publicLocation()} – " . implode(' ', $request->tagNameList())
);

header("Location: {$request->location()}");
