<?php

if (!$Viewer->permitted('admin_manage_permissions')) {
    error(403);
}

$privMan = new Gazelle\Manager\Privilege;

$privilege = $privMan->findById((int)($_REQUEST['removeid'] ?? 0));
if ($privilege) {
    authorize();
    if ($privilege->userTotal() > 0) {
        error('You cannot delete a class with users.');
    }
    $privilege->remove();
    header("Location: tools.php?action=permissions");
    exit;
}

$edit = isset($_REQUEST['id']) && $_REQUEST['id'] !== 'new';

$usersAffected = null;

if (isset($_REQUEST['submit'])) {
    authorize();
    $validator = new Gazelle\Util\Validator;
    $validator->setFields([
        ['name', true, 'string', 'You did not enter a valid name for this permission set.'],
        ['level', true, 'number', 'You did not enter a valid level for this permission set.'],
    ]);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    if ($edit) {
        $privilege = $privMan->findById((int)$_REQUEST['id']);
        if (is_null($privilege)) {
            header("Location: tools.php?action=permissions");
            exit;
        }
        if (empty($_REQUEST['secondary']) == $privilege->isSecondary() && $privilege->userTotal() > 0) {
            error("You can't toggle secondary when there are users");
        }

        $check = $privMan->findByLevel($_REQUEST['level']);
        if ($check && $privilege->id() != $check->id()) {
            error('There is already a permission class with that level.');
        }
    }

    $name         = $_REQUEST['name'];
    $forums       = $_REQUEST['forums'];
    $displayStaff = isset($_REQUEST['displaystaff']);
    $staffGroupId = $displayStaff
        ? (new Gazelle\Manager\StaffGroup)->findById((int)($_REQUEST['staffgroup'] ?? 0))?->id()
        : null;
    $level        = (int)$_REQUEST['level'];
    $secondary    = (bool)isset($_REQUEST['secondary']);
    $badge        = $secondary ? ($_REQUEST['badge'] ?? '') : '';
    $values       = [];
    foreach ($_REQUEST as $key => $perm) {
        if (str_starts_with($key, 'perm_')) {
            $values[substr($key, 5)] = (int)$perm;
        }
    }

    if (!$edit) {
        $privMan->create($name, $level, $secondary, $forums, $values, $staffGroupId, $badge, $displayStaff);
        header("Location: tools.php?action=permissions");
        exit;
    }
    $privilege->setUpdate('Badge', $badge)
        ->setUpdate('DisplayStaff', $displayStaff ? '1' : '0')
        ->setUpdate('Level', $level)
        ->setUpdate('Name', $name)
        ->setUpdate('Secondary', $secondary)
        ->setUpdate('PermittedForums', $forums)
        ->setUpdate('StaffGroup', $staffGroupId)
        ->setUpdate('`Values`', serialize($values))
        ->modify();

    $usersAffected = (new Gazelle\Manager\User)->flushUserclass($level);
}

require_once('permissions_edit.php');
