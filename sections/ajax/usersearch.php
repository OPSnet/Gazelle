<?php
/** @phpstan-var \Gazelle\User $Viewer */

$search = trim($_GET['search'] ?? '');
if (!strlen($search)) {
    json_die("failure", "no search terms");
}

echo (new Gazelle\Json\UserSearch(
    $search,
    $Viewer,
    new Gazelle\Manager\User(),
    new Gazelle\Util\Paginator(AJAX_USERS_PER_PAGE, (int)($_GET['page'] ?? 1)),
))
    ->setVersion(2)
    ->response();
