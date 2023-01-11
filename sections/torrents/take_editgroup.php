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
        echo $Twig->render('tgroup/revert-confirm.twig', [
            'auth'        => $Viewer->auth(),
            'group_id'    => $GroupID,
            'revision_id' => $RevisionID,
        ]);
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

    if (empty($_POST['image'])) {
        $Image = '';
    } else {
        $Image = $_POST['image'];
        if (!preg_match(IMAGE_REGEXP, $Image)) {
            error(display_str($Image) . " does not look like a valid image url");
        }
        $banned = (new Gazelle\Util\ImageProxy($Viewer))->badHost($Image);
        if ($banned) {
            error("Please rehost images from $banned elsewhere.");
        }
    }

    $Body = $_POST['body'];
    if ($_POST['summary']) {
        $logInfo[] = "summary: " . trim($_POST['summary']);
    }
    $RevisionID = $tgroup->createRevision($Body, $Image, $_POST['summary'], $Viewer);
}

$imageFlush = ($Image != $tgroup->showFallbackImage(false)->image());

$tgroup->setUpdate('WikiBody', $Body)
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

header('Location: ' . $tgroup->location());
