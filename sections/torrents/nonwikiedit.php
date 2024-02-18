<?php

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

authorize();

$tgMan = new Gazelle\Manager\TGroup();
$tgroup = $tgMan->findById((int)($_POST['groupid']));
if (is_null($tgroup)) {
    error(404);
}

if (!$tgroup->canEdit($Viewer)) {
    error(403);
}

$log = [];
if (isset($_POST['leech_type']) && $Viewer->permitted('torrents_freeleech')) {
    $torMan    = new Gazelle\Manager\Torrent();
    $reason    = $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::Normal->value);
    $leechType = $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Normal->value);
    $tgroup->setFreeleech(
        torMan:    $torMan,
        tracker:   new Gazelle\Tracker(),
        user:      $Viewer,
        leechType: $leechType,
        reason:    $reason,
        all:       $_POST['all'] == 'all',
    );
    $log[] = "freeleech type={$leechType->label()} reason={$reason->label()}";
}

$year = (int)trim($_POST['year']);
if ($tgroup->year() != $year) {
    $tgroup->setField('Year', $year);
    $log[] = "year {$tgroup->year()} => $year";
}

$recordLabel = trim($_POST['record_label']);
if ($tgroup->recordLabel() != $recordLabel) {
    $tgroup->setField('RecordLabel', $recordLabel);
    $log[] = "record label \"{$tgroup->recordLabel()}\" => \"$recordLabel\"";
}

$catNumber = trim($_POST['catalogue_number']);
if ($tgroup->catalogueNumber() != $catNumber) {
    $tgroup->setField('CatalogueNumber', $catNumber);
    $log[] = "cat number \"{$tgroup->catalogueNumber()}\" => \"$catNumber\"";
}

if ($tgroup->dirty()) {
    (new Gazelle\Log())->group($tgroup, $Viewer, ucfirst(implode(", ", $log)));
    $tgroup->modify();
    $tgroup->refresh();
}

header("Location: " . $tgroup->location());
