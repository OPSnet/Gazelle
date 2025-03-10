<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */

if (!$Viewer->permitted('site_torrents_notify')) {
    error(403);
}
authorize();

$releaseTypes = (new Gazelle\ReleaseType())->list();

$formId = (int)$_POST['formid'];

$filter = (new Gazelle\Notification\Filter())
    ->setYears((int)$_POST['fromyear' . $formId], (int)$_POST['toyear' . $formId])
    ->setUsers(new Gazelle\Manager\User(), $_POST['users' . $formId])
    ->setBoolean('exclude_va', isset($_POST['excludeva' . $formId]))
    ->setBoolean('new_groups_only', isset($_POST['newgroupsonly' . $formId]))
    ->setMultiLine('artist', $_POST['artists' . $formId])
    ->setMultiLine('tag', $_POST['tags' . $formId])
    ->setMultiLine('not_tag', $_POST['nottags' . $formId])
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
    error($error);
}

if ($filterId) {
    $filter->modify($Viewer->id(), $filterId);
} else {
    $filter->create($Viewer->id());
}

$Cache->delete_multi(["u_notify_" . $Viewer->id(), "notify_artists_" . $Viewer->id()]);
header('Location: user.php?action=notify');
