<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_torrents_notify')) {
    Error403::error();
}
authorize();

$releaseTypes = new ReleaseType()->list();
$formId       = (int)$_POST['formid'];
$tags         = trim($_POST['tags' . $formId]);
$notTags      = trim($_POST['nottags' . $formId]);
if (strlen($tags) > 500) {
    Error400::error('Tag list cannot exceed 500 characters');
}
if (strlen($notTags) > 500) {
    Error400::error('"Not Tag" list cannot exceed 500 characters');
}

$filter = new Notification\Filter()
    ->setYears((int)$_POST['fromyear' . $formId], (int)$_POST['toyear' . $formId])
    ->setUsers($_POST['users' . $formId])
    ->setBoolean('exclude_va', isset($_POST['excludeva' . $formId]))
    ->setBoolean('new_groups_only', isset($_POST['newgroupsonly' . $formId]))
    ->setMultiLine('artist', $_POST['artists' . $formId])
    ->setMultiLine('tag', $tags)
    ->setMultiLine('not_tag', $notTags)
    ->setMultiLine('record_label', $_POST['recordlabel' . $formId])
    ->setMultiValue('category', array_map(fn($id) => CATEGORY[$id], $_POST['categories' . $formId] ?? []))
    ->setMultiValue('format', array_map(fn($id) => FORMAT[$id], $_POST['formats' . $formId] ?? []))
    ->setMultiValue('encoding', array_map(fn($id) => ENCODING[$id], $_POST['bitrates' . $formId] ?? []))
    ->setMultiValue('media', array_map(fn($id) => MEDIA[$id], $_POST['media' . $formId] ?? []))
    ->setMultiValue('release_type', array_map(fn($id) => $releaseTypes[$id], $_POST['releasetypes' . $formId] ?? []));

$error = false;
$filterId = (int)($_POST['id' . $formId] ?? 0);
if (!$filterId) {
    $label = $_POST['label' . $formId] ?? null;
    if ($label) {
        $filter->setLabel($label);
    } else {
        $error = 'You must add a label for the filter set';
    }
}
if (!$filter->isConfigured()) {
    $error = 'You must add at least one criterion to filter by';
}
if ($error) {
    Error400::error($error);
}

if ($filterId) {
    $filter->modify($Viewer, $filterId);
} else {
    $filter->create($Viewer);
}

$Cache->delete_multi(["u_notify_" . $Viewer->id, "notify_artists_" . $Viewer->id]);
header('Location: user.php?action=notify');
