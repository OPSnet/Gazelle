<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_edit_wiki')) {
    Error403::error();
}

authorize();

$artist = new Manager\Artist()->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    Error404::error();
}

if (($_GET['action'] ?? '') === 'revert') { // if we're reverting to a previous revision
    authorize();
    $revisionId = (int)$_GET['revisionid'];
    if (!$revisionId) {
        Error400::error('No revision given to revert');
    }
    $artist->revertRevision($revisionId, $Viewer);
    header("Location: " . $artist->location());
    exit;
}

$body = trim($_POST['body']);
if ($body != $artist->body()) {
    $artist->setField('body', $body);
}

$image = trim($_POST['image']);
if ($image != $artist->image()) {
    if (!empty($image)) {
        if (!preg_match(IMAGE_REGEXP, $image)) {
            Error400::error(html_escape($image) . " does not look like a valid image url");
        }
        $banned = Util\ImageProxy::badHost($image);
        if ($banned) {
            Error400::error("Please rehost images from $banned elsewhere.");
        }
    }
    $artist->setField('image', $image);
}

$showcase = isset($_POST['showcase']);
if ($showcase != $artist->isShowcase() && $Viewer->permitted('artist_edit_vanityhouse')) {
    $artist->setField('VanityHouse', (int)$showcase);
}

$summary   = [];
$discogsId = (int)($_POST['discogs-id']);
if ($discogsId != $artist->discogs()->id) {
    $summary[] = $discogsId ? "Discogs relation set to $discogsId" : "Discogs relation cleared";
    $artist->setField('discogs', new Util\Discogs($discogsId));
}

if (isset($_POST['locked'])) {
    $artist->toggleAttr('locked', true);
    $summary[] = 'artist locked';
} elseif (isset($_POST['unlocked']) && $Viewer->permitted('users_mod')) {
    $artist->toggleAttr('locked', false);
    $summary[] = 'artist unlocked';
}

$notes = trim($_POST['summary']);
if ($notes) {
    $summary[] = "notes: $notes";
}
$artist->setField('summary', $summary)->setUpdateUser($Viewer)->modify();

header("Location: " . $artist->location());
