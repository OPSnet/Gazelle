<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$search = trim($_GET['search'] ?? '');
if (!strlen($search)) {
    json_die("failure", "no search terms");
}

echo new Json\UserSearch(
    $search,
    $Viewer,
    new Util\Paginator(AJAX_USERS_PER_PAGE, (int)($_GET['page'] ?? 1)),
)
    ->setVersion(2)
    ->response();
