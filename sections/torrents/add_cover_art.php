<?php

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}
authorize();

$summaryList = $_POST['summary'] ?? [];
$imageList   = $_POST['image'] ?? [];
if (count($imageList) != count($summaryList)) {
    error('Missing an image or a summary');
}

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)($_POST['groupid'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}

$imgProxy = new Gazelle\Util\ImageProxy($Viewer);
$logger   = new Gazelle\Log;

foreach ($imageList as $n => $image) {
    $image = trim($image);
    if (!preg_match(IMAGE_REGEXP, $image)) {
        error(display_str($image) . " does not look like a valid image url");
    }
    $banned = $imgProxy->badHost($image);
    if ($banned) {
        error("Please rehost images from $banned elsewhere.");
    }
    $tgroup->addCoverArt($image, trim($summaryList[$n]), $Viewer->id(), $logger);
}

header('Location: ' . redirectUrl($tgroup->location()));
