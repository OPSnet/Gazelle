<?php
if (!check_perms('admin_manage_permissions')) {
    error(403);
}

$id = $_REQUEST['id'] ?? null;
if ($id) {
    $Val->SetFields('name', true, 'string', 'You did not enter a valid name for this permission set.');
    $Val->SetFields('level', true, 'number', 'You did not enter a valid level for this permission set.');
    $_POST['maxcollages'] = (empty($_POST['maxcollages'])) ? 0 : $_POST['maxcollages'];
    $Val->SetFields('maxcollages', true, 'number', 'You did not enter a valid number of personal collages.');

    if (is_numeric($id)) {
        $DB->prepared_query('
            SELECT
                p.ID,
                p.Name,
                p.Level,
                p.Secondary,
                p.PermittedForums,
                p.Values,
                p.DisplayStaff,
                p.StaffGroup,
                p.badge,
                count(u.ID) + count(DISTINCT l.UserID)
            FROM permissions AS p
            LEFT JOIN users_main AS u ON (u.PermissionID = p.ID)
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID)
            WHERE p.ID = ?
            GROUP BY p.ID
            ', $id
        );
        list($id, $name, $level, $secondary, $forums, $values, $displayStaff, $staffGroup, $badge, $userCount) = $DB->next_record(MYSQLI_NUM, [5]);

        if (!check_perms('admin_manage_permissions', $level)) {
            error(403);
        }

        $values = unserialize($values);
    }

    if (!empty($_POST['submit'])) {
        $err = $Val->ValidateForm($_POST);
        if ($err) {
            error($err);
        }

        if (!is_numeric($id)) {
            if ($DB->scalar('SELECT ID FROM permissions WHERE Level = ?', $_REQUEST['level'])) {
                error('There is already a permission class with that level.');
            }
        } else if (empty($_REQUEST['secondary']) == $secondary) {
            if (!$secondary && $DB->scalar('SELECT count(*) FROM users_main WHERE PermissionID = ?', $id) ||
                 $secondary && $DB->scalar('SELECT count(*) FROM users_levels WHERE PermissionID = ?', $id)) {
                error("You can't toggle secondary when there are users");
            }
        }

        $values = [];
        foreach ($_REQUEST as $key => $perms) {
            if (substr($key, 0, 5) == 'perm_') {
                $values[substr($key, 5)] = (int)$perms;
            }
        }

        $name = $_REQUEST['name'];
        $level = $_REQUEST['level'];
        $secondary = empty($_REQUEST['secondary']) ? 0 : 1;
        $forums = $_REQUEST['forums'];
        $displayStaff = empty($_REQUEST['displaystaff']) ? '0' : '1';
        $staffGroup = $_REQUEST['staffgroup'] ?? null;
        $badge = $_REQUEST['badge'] ?? '';

        if (!$secondary) {
            $badge = '';
        }

        $values['MaxCollages'] = $_REQUEST['maxcollages'];

        if (!is_numeric($id)) {
            $DB->prepared_query('
                INSERT INTO permissions
                       (Level, Name, Secondary, PermittedForums, `Values`, DisplayStaff, StaffGroup, badge)
                VALUES (?,     ?,    ?,         ?,                ?,       ?,            ?,          ?)
                ', $level, $name, $secondary, $forums, serialize($values), $displayStaff, $staffGroup, $badge
            );
        } else {
            $DB->prepared_query('
                UPDATE permissions
                SET Level = ?,
                    Name = ?,
                    Secondary = ?,
                    PermittedForums = ?,
                    `Values` = ?,
                    DisplayStaff = ?,
                    StaffGroup = ?,
                    badge = ?
                WHERE ID = ?
                ', $level, $name, $secondary, $forums, serialize($values), $displayStaff, $staffGroup, $badge, $id
            );
            if ($secondary) {
                $DB->prepared_query("
                    SELECT DISTINCT concat('user_info_heavy_', UserID)
                    FROM users_levels
                    WHERE PermissionID = ?
                    ", $id
                );
            } else {
                $DB->prepared_query("
                    SELECT DISTINCT concat('user_info_heavy_', ID)
                    FROM users_main
                    WHERE PermissionID = ?
                    ", $id
                );
            }
            $Cache->deleteMulti(array_merge(['perm_'.$id], $DB->collect(0, false)));
        }
        $Cache->deleteMulti(['classes', 'staff']);
    }

    require(__DIR__ . '/permissions_edit.php');
} else {
    $id = $_REQUEST['removeid'] ?? null;
    if ($id) {
        if ($DB->scalar('SELECT count(*) FROM users_main WHERE PermissionID = ?', $id)) {
            error('You cannot delete a class with users.');
        }
        if ($DB->scalar('SELECT Secondary FROM permissions WHERE ID = ?', $id)) {
            $DB->prepared_query("
                SELECT DISTINCT concat('user_info_heavy_', UserID)
                FROM users_levels
                WHERE PermissionID = ?
                ", $id
            );
            $Cache->deleteMulti($DB->collect(0, false));
            $DB->prepared_query('
                DELETE FROM users_levels
                WHERE PermissionId = ?
                ', $id
            );
        }

        $DB->prepared_query('
            DELETE FROM permissions
            WHERE ID = ?
            ', $id
        );

        $Cache->delete_value('classes');
    }

    require(__DIR__ . '/permissions_list.php');
}
