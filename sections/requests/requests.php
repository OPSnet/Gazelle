<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error();
    }
}

$initial = !isset($_GET['submit']);
$search  = new Search\Request();
if (isset($_GET['type'])) {
    switch ($_GET['type']) {
        case 'bookmarks':
            $search->setBookmarker($user);
            break;
        case 'created':
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                Error403::error();
            }
            $search->setCreator($user);
            break;
        case 'voted':
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                Error403::error();
            }
            $search->setVoter($user);
            break;
        case 'filled':
            if (!$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                Error403::error();
            }
            $search->setFiller($user);
            break;
        default:
            Error404::error();
    }
    if ($initial) {
        $search->showFilled();
    }
}
if (isset($_GET['show_filled'])) {
    $search->showFilled();
}

$categoryList = array_map(fn ($c) => (int)$c, $_GET['filter_cat'] ?? []);
$search->setTag(
        trim($_GET['tags'] ?? ''),
        match ($_GET['tag_mode'] ?? 'all') {
            'all'   => Enum\SearchTag::all,
            default => Enum\SearchTag::any,
        },
    )
    ->setCategory($categoryList)
    ->setSearch(trim($_GET['search'] ?? ''))
    ->setYear((int)($_GET['year'] ?? 0));

$encodingStrict = isset($_GET['bitrates_strict']);
$formatStrict   = isset($_GET['formats_strict']);
$mediaStrict    = isset($_GET['media_strict']);
if (in_array(CATEGORY_MUSIC - 1, $categoryList)) {
    $search->setFormat($_GET['formats'] ?? [], $formatStrict)
        ->setEncoding($_GET['bitrates'] ?? [], $encodingStrict)
        ->setMedia($_GET['media'] ?? [], $mediaStrict)
        ->setReleaseType($_GET['releases'] ?? []);
} elseif (in_array(4, $categoryList) || in_array(7, $categoryList)) { // Audiobooks, Comedy
    $search->setFormat($_GET['formats'] ?? [], $formatStrict)
        ->setEncoding($_GET['bitrates'] ?? [], $encodingStrict);
}

$paginator = new Util\Paginator(REQUESTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('request/index.twig', [
    'bounty'          => $Viewer->ordinal()->value('request-bounty-vote'),
    'filter_cat'      => $categoryList,
    'encoding_strict' => $encodingStrict,
    'format_strict'   => $formatStrict,
    'media_strict'    => $mediaStrict,
    'initial'         => $initial,
    'paginator'       => $paginator,
    'release_types'   => new \Gazelle\ReleaseType()->list(),
    'result'          => $search->page($paginator->limit(), $paginator->offset()),
    'search'          => $search,
    'search_text'     => $_GET['search'] ?? null,
    'tag_mode'        => $_GET['tag_mode'] ?? 'all',
    'filtering'       => true, // false on artist page
    'type'            => $_GET['type'] ?? null,
    'user'            => $user,
    'viewer'          => $Viewer,
]);
