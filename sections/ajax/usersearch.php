<?php

use Gazelle\Util\Paginator;

$search = trim($_GET['search'] ?? '');
if (!strlen($search)) {
    json_die("failure", "no search terms");
}

$condition = check_perms('site_advanced_search')
    ? "Username LIKE concat('%', ?, '%')"
    : "Username = ?";

$total = $DB->scalar("
    SELECT count(*) FROM users_main WHERE $condition
    ", $search
);

$paginator = new Paginator(AJAX_USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$DB->prepared_query("
    SELECT um.ID,
        um.Username,
        um.Enabled,
        um.PermissionID,
        (donor.UserID IS NOT NULL) AS Donor,
        ui.Warned,
        ui.Avatar
    FROM users_main AS um
    INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
    LEFT JOIN users_levels AS donor ON (donor.UserID = um.ID
        AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
    )
    WHERE $condition
    ORDER BY Username
    LIMIT ? OFFSET ?
    ", $search, $paginator->limit(), $paginator->offset()
);
$results = $DB->to_array(0, MYSQLI_NUM);

$payload = [];
foreach ($results as $result) {
    [$userId, $username, $enabled, $permissionId, $donor, $warned, $avatar] = $result;
    $payload[] = [
        'userId'   => (int)$userId,
        'username' => $username,
        'donor'    => $donor == 1,
        'warned'   => !is_null($warned),
        'enabled'  => ($enabled == 2 ? false : true),
        'class'    => Users::make_class_string($permissionId),
        'avatar'   => $avatar,
    ];
}

json_print("success", [
    'currentPage' => $paginator->page(),
    'pages'       => (int)ceil($total / AJAX_USERS_PER_PAGE),
    'results'     => $payload,
]);
