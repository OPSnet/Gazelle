<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

/**
 * New transcode module:
 * $_GET['filter'] determines which torrents should be shown and can be uploaded (default), snatched or seeding
 * $_GET['target'] further filters which transcodes one would like to do and can be V0, 320 or all (default)
 *  Here, 'any' means that at least one of the formats V0 and/or 320 is missing and 'all' means that both of them are missing.
 *  'v0', etc. mean that this specific format is missing (but others might be present).
 *
 * Furthermore, there's $_GET['userid'] which allows to see the page as a different user would see it (specifically relevant for uploaded/snatched/seeding).
 */

if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User())->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}

$filter = $_GET['filter'] ?? 'uploaded';
$search = $_GET['search'] ?? null;
$target = $_GET['target'] ?? null;
$better = new Gazelle\Search\Transcode($user, new Gazelle\Manager\Torrent());

switch ($filter) {
    case 'seeding':
        $better->setModeSeeding();
        break;
    case 'snatched':
        $better->setModeSnatched();
        break;
    case 'uploaded':
        $better->setModeUploaded();
        break;
    default:
        break;
}
switch ($target) {
    case 'v0':
        $better->want320();
        break;
    case '320':
        $better->wantV0();
        break;
    default:
        $better->want320();
        $better->wantV0();
        break;
}
if ($search) {
    $better->setSearch($search);
}

$list = $better->list(200, 0);
shuffle($list);
$list = array_slice($list, 0, TORRENTS_PER_PAGE);

echo $Twig->render('better/search.twig', [
    'filter' => $filter,
    'list'   => $list,
    'search' => $search,
    'source' => array_map(fn ($b) => $b['source'], $list),
    'target' => $target,
    'total'  => $better->total(),
    'viewer' => $Viewer,
]);
