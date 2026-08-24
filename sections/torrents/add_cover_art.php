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

foreach ($imageList as $n => $url) {
    $image = Image::fromUntrusted($url);
    if ($image->errorMessage()) {
        Error400::error($image->errorMessage());
    }
    $tgroup->artwork()->addArtwork($image, trim($summaryList[$n]), $Viewer);
}

header('Location: ' . redirectUrl($tgroup->location()));
