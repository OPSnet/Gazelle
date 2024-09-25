<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}

authorize();

$artist = (new Gazelle\Manager\Artist())->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
}

if (($_GET['action'] ?? '') === 'revert') { // if we're reverting to a previous revision
    authorize();
    $revisionId = (int)$_GET['revisionid'];
    if (!$revisionId) {
        error('No revision given to revert');
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
            error(display_str($image) . " does not look like a valid image url");
        }
        $banned = (new Gazelle\Util\ImageProxy($Viewer))->badHost($image);
        if ($banned) {
            error("Please rehost images from $banned elsewhere.");
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
if ($discogsId != $artist->discogs()->id()) {
    $summary[] = $discogsId ? "Discogs relation set to $discogsId" : "Discogs relation cleared";
    $artist->setField('discogs', new Gazelle\Util\Discogs($discogsId));
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
