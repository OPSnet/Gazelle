<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

authorize();

if (!$Viewer->permitted('torrents_edit')) {
    Error403::error("You are not allowed to edit torrents");
}

$tgroup = new Manager\TGroup()->findById((int)$_REQUEST['groupid']);
if (is_null($tgroup)) {
    Error404::error();
}

if (($_GET['action'] ?? '') == 'revert') {
    // we're reverting to a previous revision
    $revisionId = (int)$_GET['revisionid'];
    if (!$revisionId) {
        Error400::error('No revision specified to revert');
    }
    if (!isset($_GET['confirm'])) {
        echo $Twig->render('tgroup/confirm-revert.twig', [
            'revision_id' => $revisionId,
            'tgroup'      => $tgroup,
            'viewer'      => $Viewer,
        ]);
        exit;
    }
    [$body, $image] = $tgroup->revertRevision($Viewer->id, $revisionId);
    if (is_null($body)) {
        Error404::error("Unable to find revision {$revisionId}");
    }
    $tgroup->setField('WikiImage', $image)
        ->setField('WikiBody', $body)
        ->modify();
    $tgroup->refresh();
    $tgroup->imageFlush();
    header("Location: {$tgroup->location()}");
    exit;
}

if (isset($_POST['remove'])) {
    $tgroupId = $tgroup->id;
    $name     = $tgroup->text();
    if ($tgroup->torrentIdList() !== []) {
        $requestList = array_filter(
            new Manager\Request()->findByTGroup($tgroup),
            fn ($r) => !$r->isFilled()
        );
        if ($requestList !== []) {
            Error400::error('Attempted to remove the group but it is no longer empty!');
        }
    }
    if (!$tgroup->removeTGroup()) {
        Error400::error('An attempt was made to remove the group but failed due to an internal constraint!');
    }
    $summary = trim($_POST['summary'] ?? 'none given');
    $Viewer->logger()->general("Group $tgroupId ($name) was removed by user {$Viewer->username()}, reason: $summary");
    header('Location: torrents.php');
    exit;
}

$logInfo = [];
if ($_POST['name'] !== $tgroup->name()) {
    $name = trim($_POST['name']);
    $tgroup->setField('Name', $name);
    $logInfo[] = "Renamed \"{$tgroup->name()}\" → \"$name\"";
}

$newRevision = false;
$image = trim($_POST['image']);
if ($image !== $tgroup->image()) {
    if ($image !== '') {
        if (!preg_match(IMAGE_REGEXP, $image)) {
            Error400::error(html_escape($image) . " does not look like a valid image url");
        }
        $banned = Util\ImageProxy::badHost($image);
        if ($banned) {
            Error400::error("Please rehost images from $banned elsewhere.");
        }
    }
    $tgroup->setField('WikiImage', $image);
    $newRevision = true;
}

$noArtwork = isset($_POST['no_cover_art']);
if ($noArtwork !== $tgroup->hasAttr('no-cover-art')) {
    $tgroup->toggleAttr('no-cover-art', $noArtwork);
    $logInfo[] = "No cover art exception " . ($noArtwork ? 'added' : 'removed');
}

$body = trim($_POST['body']);
if ($body !== $tgroup->description()) {
    $tgroup->setField('WikiBody', $body);
    $newRevision = true;
}

$year = (int)trim($_POST['year']);
if ($tgroup->year() !== $year) {
    $future = (int)date('Y') + 1;
    if ($year > $future && !$Viewer->permitted('users_mod')) {
        json_error(
            "You may not specify a year that far in the future. Instead, set it to $future and report the upload afterwards to have the year set as appropriate by staff."
        );
    }
    $tgroup->setField('Year', $year);
    $log[] = "year {$tgroup->year()} → $year";
}

if ($tgroup->categoryName() === 'Music') {
    $releaseType = (int)$_POST['releasetype'];
    $rt = new ReleaseType();
    $newReleaseTypeName = $rt->findNameById($releaseType);
    if (!$newReleaseTypeName) {
        Error400::error("Bad release type");
    }
    if ($releaseType != $tgroup->releaseType()) {
        $tgroup->setField('ReleaseType', $releaseType);
        $logInfo[] = "Release type {$rt->findNameById($tgroup->releaseType())} → $newReleaseTypeName";
    }

    $showcase = isset($_POST['vanity_house']);
    if ($tgroup->isShowcase() != $showcase) {
        if (!$Viewer->permitted('torrents_edit_vanityhouse')) {
            Error403::error('You are not allowed to edit the Showcase status');
        }
        $tgroup->setField('VanityHouse', $showcase ? 1 : 0);
        $logInfo[] = 'Showcase status changed to ' . ($showcase ? 'true' : 'false');
    }
}

$summary = trim($_POST['summary'] ?? '');
if ($summary) {
    $logInfo[] = "summary: $summary";
}
if ($newRevision) {
    $tgroup->createRevision($body, $image, $summary);
}

if ($Viewer->permitted('torrents_freeleech')) {
    $torMan    = new Manager\Torrent();
    $leechType = $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Normal->value);
    $reason    = $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::Normal->value);
    $tgroup->setFreeleech(
        all:       $_POST['all'] == 'all',
        leechType: $leechType,
        reason:    $reason,
        user:      $Viewer,
    );
    $logInfo[] = "freeleech type={$leechType->label()} reason={$reason->label()}";
}

if ($tgroup->dirty()) {
    $tgroup->modify();
    $tgroup->refresh();
    $tgroup->imageFlush();
    if ($logInfo) {
        $tgroup->logger()->group($tgroup, $Viewer, implode(', ', $logInfo));
    }
}

header("Location: {$tgroup->location()}");
