<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$type = Enum\UserTorrentSearch::tryFrom($_GET['type'] ?? '');
if (is_null($type)) {
    json_error("bad type");
}
foreach (['limit', 'offset', 'page'] as $key) {
    if (isset($_GET[$key]) && !ctype_digit($_GET[$key])) {
        json_error("non-numeric value for {$key}");
    }
}
if (isset($_GET['offset']) && isset($_GET['page'])) {
    json_error('can only use one of offset or page');
}
if (isset($_GET['offset']) && (int)$_GET['offset'] < 0) {
    json_error('invalid offset parameter, must be 0 or greater');
}
if (isset($_GET['page']) && (int)$_GET['page'] < 1) {
    json_error('invalid page parameter, must be 1 or greater');
}

$limit = min((int)($_GET['limit'] ?? 500), 500);
if ($limit < 1) {
    json_error('invalid limit parameter, must be 1 or greater');
}
$offset = isset($_GET['page']) ? (int)($_GET['page'] - 1) * $limit : (int)($_GET['offset'] ?? 0);

// We accept id to match RED, but userid is the better param name and matches user_recents
$user = new Manager\User()->findById((int)($_GET['userid'] ?? $_GET['id'] ?? 0));
if (is_null($user)) {
    json_error("bad userid");
}
if (!$user->propertyVisible($Viewer, str_replace('-unseeded', '', $type->value))) {
    json_error('user has hidden this');
}

echo new Json\UserTorrents($user, $Viewer, $type, $limit, $offset)->response();
