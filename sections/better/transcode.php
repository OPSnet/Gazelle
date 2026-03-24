<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

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
        Error403::error();
    }
    $user = new Manager\User()->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
}

$better = new Search\Transcode($user);
$filter = $_GET['filter'] ?? 'uploaded';
try {
    $better->setMode(Enum\BetterFilter::{$filter});
} catch (\Error) {
    Error400::error('Unknown filter.');
}
$target = $_GET['target'] ?? 'all';
try {
    $better->setEncoding(Enum\BetterEncoding::from($target));
} catch (\ValueError) {
    Error400::error('Unknown target.');
}
$search = $_GET['search'] ?? null;
if ($search) {
    $better->setSearch($search);
}

$page = $better->page(200, 0);
shuffle($page);
$page = array_slice($page, 0, TORRENTS_PER_PAGE);

echo $Twig->render('better/search.twig', [
    'filter' => $filter,
    'list'   => $page,
    'search' => $search,
    'source' => array_map(fn ($b) => $b['source'], $page),
    'target' => $target,
    'total'  => $better->total(),
    'viewer' => $Viewer,
]);
