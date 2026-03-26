<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_edit_wiki')) {
    Error403::error();
}
authorize();

$summaryList = $_POST['summary'] ?? [];
$imageList   = $_POST['image'] ?? [];
if (count($imageList) !== count($summaryList)) {
    Error400::error('Missing an image or a summary');
}

$tgroup = new Manager\TGroup()->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}

foreach ($imageList as $n => $image) {
    $image = trim($image);
    if (!preg_match(IMAGE_REGEXP, $image)) {
        Error400::error(html_escape($image) . " does not look like a valid image url");
    }
    $banned = Util\ImageProxy::badHost($image);
    if ($banned) {
        Error400::error("Please rehost images from $banned elsewhere.");
    }
    $tgroup->artwork()->addArtwork($image, trim($summaryList[$n]), $Viewer);
}

header('Location: ' . redirectUrl($tgroup->location()));
