<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_permissions')) {
    Error403::error();
}

$privMan = new Manager\Privilege();

$privilege = $privMan->findById((int)($_REQUEST['removeid'] ?? 0));
if ($privilege) {
    authorize();
    if ($privilege->userTotal() > 0) {
        Error400::error('You cannot delete a class with users.');
    }
    $privilege->remove();
    header("Location: tools.php?action=userclass");
    exit;
}

$edit = isset($_REQUEST['id']) && $_REQUEST['id'] !== 'new';

$usersAffected = null;

if (isset($_REQUEST['submit'])) {
    authorize();
    $validator = new Util\Validator();
    $validator->setFields([
        ['name', true, 'string', 'You did not enter a valid name for this permission set.'],
        ['level', true, 'number', 'You did not enter a valid level for this permission set.'],
    ]);
    if (!$validator->validate($_POST)) {
        Error400::error($validator->errorMessage());
    }

    if ($edit) {
        $privilege = $privMan->findById((int)$_REQUEST['id']);
        if (is_null($privilege)) {
            header("Location: tools.php?action=userclass");
            exit;
        }
        if (empty($_REQUEST['secondary']) == $privilege->isSecondary() && $privilege->userTotal() > 0) {
            Error400::error("You can't toggle secondary when there are users");
        }

        $check = $privMan->findByLevel((int)$_REQUEST['level']);
        if ($check && $privilege->id() != $check->id()) {
            Error400::error('There is already a permission class with that level.');
        }
    }

    $name         = $_REQUEST['name'];
    $forums       = $_REQUEST['forums'];
    $displayStaff = isset($_REQUEST['displaystaff']);
    $staffGroupId = $displayStaff
        ? new Manager\StaffGroup()->findById((int)($_REQUEST['staffgroup'] ?? 0))?->id()
        : null;
    $level         = (int)$_REQUEST['level'];
    $secondary     = (int)isset($_REQUEST['secondary']);
    $badge         = $secondary ? ($_REQUEST['badge'] ?? '') : '';
    $privilegeList = [];
    foreach (array_map('strval', array_keys($_POST)) as $key) {
        if (str_starts_with($key, 'perm_')) {
            $privilegeList[substr($key, 5)] = true;
        }
    }

    if (!$edit) {
        $privMan->create(
            $name,
            $level,
            $secondary,
            $forums,
            $privilegeList,
            $staffGroupId,
            $badge,
            $displayStaff
        );
        header("Location: tools.php?action=userclass");
        exit;
    }
    $privilege->setField('Badge', $badge)
        ->setField('DisplayStaff', $displayStaff ? '1' : '0')
        ->setField('Level', $level)
        ->setField('Name', $name)
        ->setField('Secondary', $secondary)
        ->setField('PermittedForums', $forums)
        ->setField('StaffGroup', $staffGroupId)
        ->setField('`Values`', serialize($privilegeList))
        ->modify();

    $usersAffected = new Manager\User()->flushUserclass($privilege->id());
}

require __DIR__ . '/userclass_edit.php';
