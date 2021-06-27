<?php
if (!check_perms('site_torrents_notify')) {
    error(403);
}
authorize();

$formId = (int)$_POST['formid'];
$filterId = (int)$_POST['id'.$formId];

$filter = (new Gazelle\Notification\Filter)
    ->setLabel($_POST['label' . $formId])
    ->setYears((int)$_POST['fromyear' . $formId], (int)$_POST['toyear' . $formId])
    ->setUsers($_POST['users' . $formId])
    ->setBoolean('exclude_va', isset($_POST['excludeva' . $formId]))
    ->setBoolean('new_groups_only', isset($_POST['newgroupsonly' . $formId]))
    ->setMultiLine('artist', $_POST['artists' . $formId])
    ->setMultiLine('tag', $_POST['tags' . $formId])
    ->setMultiLine('not_tag', $_POST['nottags' . $formId])
    ->setMultiValue('category', $_POST['categories' . $formId])
    ->setMultiValue('format', $_POST['formats' . $formId])
    ->setMultiValue('encoding', $_POST['bitrates' . $formId])
    ->setMultiValue('media', $_POST['media' . $formId])
    ->setMultiValue('release_type', $_POST['releasetypes' . $formId]);

if (!$filter->isConfigured()) {
    $error = 'You must add at least one criterion to filter by';
} elseif (!$filter->hasLabel() && !$filterId) {
    $error = 'You must add a label for the filter set';
}
if ($error) {
    error($error);
    header('Location: user.php?action=notify');
    exit;
}

if ($filterId) {
    $filter->modify($Viewer->id(), $filterId);
} else {
    $filter->create($Viewer->id());
}

$Cache->deleteMulti(["notify_filters_{$LoggedUser['ID']}", "notify_artists_{$LoggedUser['ID']}"]);
header('Location: user.php?action=notify');
