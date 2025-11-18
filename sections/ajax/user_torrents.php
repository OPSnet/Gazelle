<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['type']) || !in_array($_GET['type'], ['snatched', 'snatched-unseeded', 'seeding', 'leeching', 'uploaded', 'uploaded-unseeded', 'downloaded'])) {
    json_error("bad type");
}
$type = (string)$_GET['type'];

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
$offset = isset($_GET['page']) ? (int)($_GET['page'] - 1) * $limit : (int)($_GET['offset'] ?? 0);
if ($limit < 1) {
    json_error('invalid limit parameter, must be 1 or greater');
}

// We accept id to match RED, but userid is the better param name and matches user_recents
$user = new Manager\User()->findById((int)($_GET['userid'] ?? $_GET['id'] ?? 0));
if (is_null($user)) {
    json_error("bad userid");
}
if (!$user->propertyVisible($Viewer, str_replace('-unseeded', '', $type))) {
    json_error('user has hidden this');
}

echo new Json\UserTorrents($user, $Viewer)
    ->setType($type)
    ->setLimit($limit)
    ->setOffset($offset)
    ->response();
