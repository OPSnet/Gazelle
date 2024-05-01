<?php

authorize();

$requestMan = new Gazelle\Manager\Request();
$request = $requestMan->findById((int)($_POST['requestid'] ?? 0));
if (is_null($request)) {
    error(404);
}

if (!$request->canEdit($Viewer)) {
    error(403);
}

if (!isset($_POST['type'])) {
    $categoryId   = $request->categoryId();
    $categoryName = $request->categoryName();
} else {
    $categoryName = $_POST['type'];
    $categoryId   = array_search($categoryName, CATEGORY);
    if ($categoryId === false) {
        error('The upload category is corrupt');
    }
    $categoryId++; // array offset to id
    if ($categoryId != $request->categoryId()) {
        $request->setField('CategoryID', $categoryId);
    }
}

$artistRole = [];
$tags       = null;
$tgroup     = null;

while (true) {
    $validator = new Gazelle\Util\Validator();
    if (isset($_POST['description'])) {
        $validator->setField('description', true, 'string', 'You forgot to enter a description.', ['maxlength' => 32000]);
    }
    if (isset($_POST['image'])) {
        $validator->setField('image', false, 'image',  '');
    }
    if (isset($_POST['tags'])) {
        $validator->setField('tags', true, 'string', 'You forgot to enter any tags!', ['maxlength' => 255]);
    }
    if (isset($_POST['title'])) {
        $validator->setField('title', true, 'string', 'You forgot to enter the title!', ['maxlength' => 255]);
    }
    if (isset($_POST['year'])) {
        $validator->setField('year', true, 'number', 'The year of the release must be entered.', ['maxlength' => date('Y') + 2]);
    }

    if (!$validator->validate($_POST)) {
        $error = $validator->errorMessage();
        break;
    }

    if (isset($_POST['description'])) {
        $description = trim($_POST['description']);
        if ($description != $request->description()) {
            $request->setField('Description', $description);
        }
    }

    if (isset($_POST['image'])) {
        $image = trim($_POST['image']);
        if ($image != $request->image()) {
            $request->setField('Image', $image);
        }
    }

    if (isset($_POST['title'])) {
        $title = trim($_POST['title']);
        if ($title != $request->title()) {
            $request->setField('Title', $title);
        }
    }

    if (isset($_POST['year'])) {
        $year = (int)$_POST['year'];
        if ($year == 0) {
            $year = null;
        }
        if ($year != $request->year()) {
            $request->setField('Year', $year);
        }
    }

    if (isset($_POST['releasetype'])) {
        $releaseType = (int)$_POST['releasetype'];
        if (!(new Gazelle\ReleaseType())->findNameById($releaseType)) {
            $error = 'Please pick a release type';
            break;
        }
        if ($releaseType != $request->releaseType()) {
            $request->setField('ReleaseType', $releaseType);
        }
    }

    if (!isset($_POST['formats'])) {
        $format = null;
    } else {
        $format = new Gazelle\Request\Format(isset($_POST['all_formats']), $_POST['formats'] ?? []);
        if (!$format->isValid()) {
            $error = 'You must require at least one valid format';
            break;
        }
        if ($format->dbValue() != $request->legacyFormatList()) {
            $request->setField('FormatList', $format->dbValue());
        }
    }

    if (!isset($_POST['bitrates'])) {
        $encoding = null;
    } else {
        $encoding = new Gazelle\Request\Encoding(isset($_POST['all_bitrates']), $_POST['bitrates'] ?? []);
        if (!$encoding->isValid()) {
            $error = 'You must require at least one valid encoding';
            break;
        }
        if ($encoding->dbValue() != $request->legacyEncodingList()) {
            $request->setField('BitrateList', $encoding->dbValue());
        }
    }

    if (!isset($_POST['media'])) {
        $media = null;
    } else {
        $media = new Gazelle\Request\Media(isset($_POST['all_media']), $_POST['media'] ?? []);
        if (!$media->isValid()) {
            $error = 'You must require at least one valid media';
            break;
        }
        if ($media->dbValue() != $request->legacyMediaList()) {
            $request->setField('MediaList', $media->dbValue());
        }
    }

    if (!($format?->exists('FLAC') && $media?->exists('CD'))) {
        $request->setField('Checksum', 0);
        $request->setField('LogCue', '');
    } else {
        $logCue = new Gazelle\Request\LogCue(
            needCue:         isset($_POST['needcue']),
            needLog:         isset($_POST['needlog']),
            needLogChecksum: isset($_POST['needcksum']),
            minScore:        (int)($_POST['minlogscore'] ?? $request->needLogScore()),
        );
        if ($logCue->needLogChecksum() != $request->needLogChecksum()) {
            $request->setField('Checksum', (int)$logCue->needLogChecksum());
        }
        if ($logCue->dbValue() != $request->descriptionLogCue()) {
            $request->setField('LogCue', $logCue->dbValue());
        }
    }

    if ($format?->exists('FLAC') && !($encoding?->exists('Lossless') || $encoding?->exists('24bit Lossless'))) {
        $error = 'You selected FLAC as a format but no encoding to fill it (Lossless and/or 24bit Lossless)';
        break;
    }

    if (!empty($_POST['groupid'])) {
        $tgroupId = preg_match(TGROUP_REGEXP, trim($_POST['groupid']), $match)
            ? (int)$match['id']
            : (int)$_POST['groupid'];
        if ($tgroupId > 0) {
            $tgroup = (new Gazelle\Manager\TGroup())->findById($tgroupId);
            if (is_null($tgroup)) {
                $error = 'The torrent group, if entered, must correspond to a music torrent group on the site.';
                break;
            }
            if ($request->tgroupId() != $tgroup->id()) {
                $request->setField('GroupID', $tgroup->id());
            }
        }
    }

    // final thing to check
    if ($categoryName === 'Music' && $Viewer->permittedAny('site_edit_requests', 'site_moderate_requests')) {
        if (empty($_POST['artists'])) {
            $error = 'You did not enter any artists.';
            break;
        }

        $artistList = $_POST['artists'];
        $roleList = $_POST['importance'];
        $main = 0;
        $seen = [];
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
        }
    }

    break; // all good!
}

if (!empty($error)) {
    require_once('edit.php');
    exit;
}

if (isset($_POST['cataloguenumber'])) {
    $catalogueNumber = trim($_POST['cataloguenumber']);
    if ($catalogueNumber != $request->catalogueNumber()) {
        $request->setField('CatalogueNumber', $catalogueNumber);
    }
}
if (isset($_POST['recordlabel'])) {
    $recordLabel = trim($_POST['recordlabel']);
    if ($recordLabel != $request->recordLabel()) {
        $request->setField('RecordLabel', $recordLabel);
    }
}
if (isset($_POST['oclc'])) {
    $oclc = trim($_POST['oclc']);
    if ($oclc != $request->oclc()) {
        $request->setField('OCLC', $oclc);
    }
}

$request->modify();
if ($categoryName === 'Music' && $Viewer->permittedAny('site_edit_requests', 'site_moderate_requests')) {
    $request->artistRole()->set($artistRole, new Gazelle\Manager\Artist());
}
if (isset($_POST['tags'])) {
    $request->setTagList(
        array_unique(array_map('trim', explode(',', trim($_POST['tags'])))),
        $Viewer,
        new Gazelle\Manager\Tag(),
    );
}
$request->updateSphinx();
$tgroup?->flush();

header("Location: " . $request->location());
