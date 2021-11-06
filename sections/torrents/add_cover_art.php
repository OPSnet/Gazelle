<?php

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}
authorize();

$tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_POST['groupid']);
if (is_null($tgroup)) {
    error(404);
}
$summaryList = $_POST['summary'];
$imageList = $_POST['image'];
if (count($imageList) != count($summaryList)) {
    error('Missing an image or a summary');
}

$imgProxy = new Gazelle\Util\ImageProxy;
$logger = new Gazelle\Log;
for ($i = 0, $end = count($imageList); $i < $end; $i++) {
    $image = trim($imageList[$i]);
    if (!preg_match(IMAGE_REGEXP, $image)) {
        error(display_str($image) . " does not look like a valid image url");
    }
    $banned = $imgProxy->badHost($image);
    if ($banned) {
        error("Please rehost images from $banned elsewhere.");
    }
    $tgroup->addCoverArt($image, trim($summaryList[$i]), $Viewer->id(), $logger);
}

header('Location: ' . redirectUrl($tgroup->url()));
