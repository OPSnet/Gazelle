<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$privMan = new Gazelle\Manager\Privilege;

$privilege = $privMan->findById((int)($_REQUEST['removeid'] ?? 0));
if ($privilege) {
    if ($privilege->userTotal() > 0) {
        error('You cannot delete a class with users.');
    }
    $privilege->remove();
    require_once('permissions_list.php');
    exit;
}

if (!isset($_REQUEST['id'])) {
    require_once('permissions_list.php');
    exit;
}

$privilege = $privMan->findById((int)($_REQUEST['id'] ?? 0));
if (!isset($_REQUEST['submit'])) {
    require_once('permissions_edit.php');
    exit;
}

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['name', true, 'string', 'You did not enter a valid name for this permission set.'],
    ['level', true, 'number', 'You did not enter a valid level for this permission set.'],
]);
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}
$check = $privMan->findByLevel($_REQUEST['level']);
if ($privilege && $check && $privilege->id() != $check->id()) {
    error('There is already a permission class with that level.');
}

if ($privilege && empty($_REQUEST['secondary']) == $privilege->isSecondary() && $privilege->userTotal() > 0) {
    error("You can't toggle secondary when there are users");
}

$name         = $_REQUEST['name'];
$forums       = $_REQUEST['forums'];
$staffGroup   = isset($_REQUEST['staffgroup']);
$displayStaff = isset($_REQUEST['displaystaff']);
$level        = (int)$_REQUEST['level'];
$secondary    = (int)isset($_REQUEST['secondary']);
$badge        = $secondary ? ($_REQUEST['badge'] ?? '') : '';
$values       = [];
foreach ($_REQUEST as $key => $perm) {
    if (substr($key, 0, 5) == 'perm_') {
        $values[substr($key, 5)] = (int)$perm;
    }
}

if (is_null($privilege)) {
    $privilege = $privMan->create($name, $level, $secondary, $forums, $values, $staffGroup, $badge, $displayStaff);
} else {
    if ($badge != $privilege->badge()) {
        $privilege->setUpdate('Badge', $badge);
    }
    if ($forums != $privilege->displayStaff()) {
        $privilege->setUpdate('DisplayStaff', $displayStaff);
    }
    if ($level != $privilege->level()) {
        $privilege->setUpdate('Level', $level);
    }
    if ($name != $privilege->name()) {
        $privilege->setUpdate('Name', $name);
    }
    if ((bool)$secondary != $privilege->isSecondary()) {
        $privilege->setUpdate('Secondary', $secondary);
    }
    if ($forums != $privilege->permittedForums()) {
        $privilege->setUpdate('PermittedForums', $forums);
    }
    if ($forums != $privilege->staffGroup()) {
        $privilege->setUpdate('StaffGroup', $staffGroup);
    }
    $privilege->setUpdate('`Values`', serialize($values))
        ->modify();
}
$Cache->deleteMulti(['classes', 'staff']);
require_once('permissions_edit.php');
