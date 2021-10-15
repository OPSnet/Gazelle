<?php
authorize();

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}
if (!$Viewer->permitted('torrents_edit_vanityhouse') && isset($_POST['vanity_house'])) {
    error(403);
}
$tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_REQUEST['groupid']);
if (is_null($tgroup)) {
    error(404);
}
$GroupID = $tgroup->id();

$logInfo = [];
if (($_GET['action'] ?? '') == 'revert') {
    // we're reverting to a previous revision
    $RevisionID = (int)$_GET['revisionid'];
    if (!$RevisionID) {
        error(0);
    }
    if (empty($_GET['confirm'])) {
        View::show_header('Group Edit');
        echo $Twig->render('tgroup/revert-confirm.twig', [
            'auth'        => $Viewer->auth(),
            'group_id'    => $GroupID,
            'revision_id' => $RevisionID,
        ]);
        View::show_footer();
        exit;
    }
    $revert = $tgroup->revertRevision($Viewer->id(), $RevisionID);
    if (is_null($revert)) {
        error(404);
    }
    [$Body, $Image] = $revert;
} else {
    // edit, variables are passed via POST
    $ReleaseType = (int)$_POST['releasetype'];
    $rt = new Gazelle\ReleaseType;
    $newReleaseTypeName = $rt->findNameById($ReleaseType);
    if ($tgroup->categoryId() == 1 && !$newReleaseTypeName || $tgroup->categoryId() != 1 && $ReleaseType) {
        error(403);
    }
    if ($ReleaseType != $tgroup->releaseType()) {
        $tgroup->setUpdate('ReleaseType', $ReleaseType);
        $logInfo[] = "Release type changed from "
            . $rt->findNameById($tgroup->releaseType())
            . " to $newReleaseTypeName";
    }

    if ($Viewer->permitted('torrents_edit_vanityhouse')) {
        $showcase = isset($_POST['vanity_house']) ? 1 : 0;
        if ($tgroup->isShowcase() != $showcase) {
            $tgroup->setUpdate('VanityHouse', $showcase);
            $logInfo[] = 'Vanity House status changed to '. ($showcase ? 'true' : 'false');
        }
    }

    $Image = $_POST['image'];
    if (!preg_match(IMAGE_REGEXP, $Image)) {
        $Image = '';
    }
    ImageTools::blacklisted($Image);
    if ($Image) {
        foreach (IMAGE_HOST_BANNED as $banned) {
            if (stripos($banned, $Image) !== false) {
                error("Please rehost images from $banned elsewhere.");
            }
        }
    }
    $Body = $_POST['body'];
    if ($_POST['summary']) {
        $logInfo[] = "summary: " . trim($_POST['summary']);
    }
    $RevisionID = $tgroup->createRevision($Viewer->id(), $Image, $Body, $_POST['summary']);
}

$imageFlush = ($Image != $tgroup->showFallbackImage(false)->image());

$tgroup->setUpdate('RevisionID', $RevisionID)
    ->setUpdate('WikiBody', $Body)
    ->setUpdate('WikiImage', $Image)
    ->modify();

if ($imageFlush) {
    $tgroup->imageFlush();
}

$noCoverArt = isset($_POST['no_cover_art']);
if ($noCoverArt != $tgroup->hasNoCoverArt()) {
    $tgroup->toggleNoCoverArt($noCoverArt);
    $logInfo[] = "No cover art exception " . ($noCoverArt ? 'added' : 'removed');
}
if ($logInfo) {
    (new Gazelle\Log)->group($tgroup->id(), $Viewer->id(), implode(', ', $logInfo));
}

header('Location: ' . $tgroup->url());
