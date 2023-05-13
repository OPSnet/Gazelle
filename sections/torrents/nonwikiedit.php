<?php

authorize();

$tgMan = new Gazelle\Manager\TGroup;
$tgroup = $tgMan->findById((int)($_POST['groupid']));
if (is_null($tgroup)) {
    error(404);
}

if (!$tgroup->canEdit($Viewer)) {
    error(403);
}

$log = [];
if (isset($_POST['freeleechtype']) && $Viewer->permitted('torrents_freeleech')) {
    if (!in_array($_POST['freeleechreason'] ?? '', ['0', '1', '2', '3'])) {
        error(404);
    }
    $reason = $_POST['freeleechreason'];
    $free = in_array($_POST['freeleechtype'], ['0', '1', '2']) ? $_POST['freeleechtype'] : '0';
    $log[] = "freeleech type=$free reason=$reason";
    (new Gazelle\Manager\Torrent)->setFreeleech($Viewer, $tgroup->torrentIdList(), $free, $reason, false, false);
}

$year = (int)trim($_POST['year']);
if ($tgroup->year() != $year) {
    $tgroup->setUpdate('Year', $year);
    $log[] = "year {$tgroup->year()} => $year";
}

$recordLabel = trim($_POST['record_label']);
if ($tgroup->recordLabel() != $recordLabel) {
    $tgroup->setUpdate('RecordLabel', $recordLabel);
    $log[] = "record label \"{$tgroup->recordLabel()}\" => \"$recordLabel\"";
}

$catNumber = trim($_POST['catalogue_number']);
if ($tgroup->catalogueNumber() != $catNumber) {
    $tgroup->setUpdate('CatalogueNumber', $catNumber);
    $log[] = "cat number \"{$tgroup->catalogueNumber()}\" => \"$catNumber\"";
}

if ($tgroup->dirty()) {
    (new Gazelle\Log)->group($tgroup->id(), $Viewer->id(), ucfirst(implode(", ", $log)));
    $tgroup->modify();
    $tgroup->refresh();
}

header("Location: " . $tgroup->location());
