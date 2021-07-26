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

$logger = new Gazelle\Log;
for ($i = 0, $end = count($imageList); $i < $end; $i++) {
    $image = trim($imageList[$i]);
    if (ImageTools::blacklisted($image, true) || !preg_match(IMAGE_REGEXP, $image)) {
        continue;
    }
    $tgroup->addCoverArt($image, trim($summaryList[$i]), $Viewer->id(), $logger);
}

header("Location: " . redirectUrl("torrents.php?id=" . $tgroup->id()));
