<?php
/** @phpstan-var \Gazelle\User $Viewer */

use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

if (!$Viewer->permitted('site_edit_wiki')) {
    error(403);
}

authorize();

$collageMan = new Gazelle\Manager\Collage();
$collage    = $collageMan->findById((int)($_POST['collageid'] ?? 0));
if (is_null($collage)) {
    error(404);
}
if (!$collage->isPersonal()) {
    if (!$Viewer->permitted('site_collages_manage')) {
        error(403);
    }
} elseif (!$collage->isOwner($Viewer) && !$Viewer->permitted('site_collages_delete')) {
    // only owner or mod+ can edit personal collages
    error(403);
}

if (isset($_POST['name'])) {
    $name = trim($_POST['name']);
    $check = $collageMan->findByName($name);
    if ($check && $check->id() !== $collage->id()) {
        if ($check->isDeleted()) {
            $Err = 'A collage with that name already exists but needs to be recovered, please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = "A collage with that name already exists: {$check->link()}.";
        }
        $ErrNoEscape = true;
        include 'edit.php';
        exit;
    }
    if ($collage->isOwner($Viewer)) {
        if (!$Viewer->permitted('site_collages_renamepersonal') && !stristr($name, $Viewer->username())) {
            error("Your personal collage's title must include your username.");
        }
    }
}

if (!isset($_POST['regen-tags'])) {
    $collage->setField(
        'TagList',
        (new Gazelle\Manager\Tag())
            ->normalize(str_replace(',', ' ', (string)$_POST['tags']))
    );
} else {
    $tagList = $collage->rebuildTagList();
    if (count($tagList) > 2) {
        $collage->setField('TagList', implode(' ', $tagList));
    }
}
$collage->setField('Description', trim($_POST['description']));

if (
    isset($_POST['featured'])
    && (
        ($collage->isPersonal() && $collage->isOwner($Viewer))
        || $Viewer->permitted('site_collages_delete')
    )
) {
    $collage->setFeatured();
}

if (
    ($collage->isPersonal() && $collage->isOwner($Viewer) && $Viewer->permitted('site_collages_renamepersonal'))
    || $Viewer->permitted('site_collages_delete')
) {
    $collage->setField('Name', trim($_POST['name']));
}

if (isset($_POST['category']) && isset(COLLAGE[$_POST['category']]) && (int)$_POST['category'] !== $collage->categoryId()) {
    if ($collage->isPersonal() && !$Viewer->permitted('site_collages_delete')) {
        error(403);
    }
    $collage->setField('CategoryID', (int)$_POST['category']);
}

if ($Viewer->permitted('site_collages_delete')) {
    if (isset($_POST['locked']) != $collage->isLocked()) {
        $collage->toggleLocked();
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroups'] == 0 || is_number($_POST['maxgroups'])) && $_POST['maxgroups'] != $collage->maxGroups()) {
        $collage->setField('MaxGroups', (int)$_POST['maxgroups']);
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroupsperuser'] == 0 || is_number($_POST['maxgroupsperuser'])) && $_POST['maxgroupsperuser'] != $collage->maxGroupsPerUser()) {
        $collage->setField('MaxGroupsPerUser', (int)$_POST['maxgroupsperuser']);
    }
}
$collage->toggleAttr('sort-newest', isset($_POST['addition']));

$collage->modify();

if ($Viewer->permitted('admin_freeleech') && isset($_POST['change_leech'])) {
    $torMan = new \Gazelle\Manager\Torrent();
    if (!isset($_POST['neutral'])) {
        $threshold = 0;
    } else {
        $threshold = get_bytes(
            (int)($_POST['size'] ?? NEUTRAL_LEECH_THRESHOLD)
            . trim($_POST['unit'] ?? NEUTRAL_LEECH_UNIT)
        );
    }
    $collage->setFreeleech(
        torMan:    $torMan,
        tracker:   new \Gazelle\Tracker(),
        user:      $Viewer,
        leechType: $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Normal->value),
        reason:    $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::Normal->value),
        threshold: $threshold,
        all:       $_POST['all'] == 'all',
    );
}

header('Location: ' . $collage->location());
