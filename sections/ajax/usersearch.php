<?php

use Gazelle\Util\Paginator;

$search = trim($_GET['search'] ?? '');
if (!strlen($search)) {
    json_die("failure", "no search terms");
}

$condition = $Viewer->permitted('site_advanced_search')
    ? "Username LIKE concat('%', ?, '%')"
    : "Username = ?";

$total = $DB->scalar("
    SELECT count(*) FROM users_main WHERE $condition
    ", $search
);

$paginator = new Paginator(AJAX_USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$DB->prepared_query("
    SELECT um.ID
    FROM users_main AS um
    WHERE $condition
    ORDER BY Username
    LIMIT ? OFFSET ?
    ", $search, $paginator->limit(), $paginator->offset()
);
$userIds = $DB->collect(0, false);

$payload = [];
$userMan = new Gazelle\Manager\User;
foreach ($userIds as $userId) {
    $user = $userMan->findById($userId);
    $payload[] = [
        'userId'   => $user->id(),
        'username' => $user->username(),
        'donor'    => $user->isDonor(),
        'warned'   => $user->isWarned(),
        'enabled'  => $user->isEnabled(),
        'class'    => $user->userclassName(),
        'avatar'   => $user->avatar(),
    ];
}

json_print("success", [
    'currentPage' => $paginator->page(),
    'pages'       => (int)ceil($total / AJAX_USERS_PER_PAGE),
    'results'     => $payload,
]);
