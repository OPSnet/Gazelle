<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['userid'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error(404);
    }
}

$search = new Gazelle\Search\Request(new Gazelle\Manager\Request());
$initial = !isset($_GET['submit']);
$bookmarkView = false;

if (empty($_GET['type'])) {
    $Title = 'Requests';
} else {
    // Show filled defaults to on only for viewing types
    if ($initial) {
        $_GET['show_filled'] = "on";
    }
    switch ($_GET['type']) {
        case 'bookmarks':
            $search->setBookmarker($user);
            $bookmarkView = true;
            break;
        case 'created':
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                error(403);
            }
            $search->setCreator($user);
            break;
        case 'voted':
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                error(403);
            }
            $search->setVoter($user);
            break;
        case 'filled':
            if (!$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                error(403);
            }
            $search->setFiller($user);
            break;
        default:
            error(404);
    }
}

if (!$initial && empty($_GET['showall'])) {
    $search->setVisible(true);
}

// We don't want to show filled by default on plain requests.php,
// but we do show it by default if viewing a $_GET['type'] page
if (($initial && !isset($_GET['type'])) || (!$initial && !isset($_GET['show_filled']))) {
    $search->showUnfilled();
}
$releaseTypes   = (new \Gazelle\ReleaseType())->list();
$encodingStrict = isset($_GET['bitrates_strict']);
$formatStrict   = isset($_GET['formats_strict']);
$mediaStrict    = isset($_GET['media_strict']);
$categoryList   = array_map(fn ($c) => (int)$c, $_GET['filter_cat'] ?? []);

$search->setCategory($categoryList)
    ->setTag($_GET['tags'] ?? '', $_GET['tag_mode'] ?? 'all')
    ->setText($_GET['search'] ?? '');

if (in_array(CATEGORY_MUSIC - 1, $categoryList)) {
    $search->setFormat($_GET['formats'] ?? [], $formatStrict)
        ->setEncoding($_GET['bitrates'] ?? [], $encodingStrict)
        ->setMedia($_GET['media'] ?? [], $mediaStrict)
        ->setReleaseType($_GET['releases'] ?? [], $releaseTypes);
} elseif (in_array(4, $categoryList) || in_array(7, $categoryList)) { // Audiobooks, Comedy
    $search->setFormat($_GET['formats'] ?? [], $formatStrict)
        ->setEncoding($_GET['bitrates'] ?? [], $encodingStrict);
}

if (isset($_GET['requestor'])) {
    $requestor = (int)$_GET['requestor'];
    if ($requestor) {
        $search->setRequestor($requestor);
    } else {
        error(404);
    }
}

if (isset($_GET['year'])) {
    $search->setYear((int)$_GET['year']);
}

$header = new Gazelle\Util\SortableTableHeader('created', [
    'year'     => ['dbColumn' => 'year',       'defaultSort' => 'desc', 'text' => 'Year'],
    'votes'    => ['dbColumn' => 'votes',      'defaultSort' => 'desc', 'text' => 'Votes'],
    'bounty'   => ['dbColumn' => 'bounty',     'defaultSort' => 'desc', 'text' => 'Bounty'],
    'filled'   => ['dbColumn' => 'timefilled', 'defaultSort' => 'desc', 'text' => 'Filled'],
    'created'  => ['dbColumn' => 'timeadded',  'defaultSort' => 'desc', 'text' => 'Created'],
    'lastvote' => ['dbColumn' => 'lastvote',   'defaultSort' => 'desc', 'text' => 'Last Vote'],
    'random'   => ['dbColumn' => 'RAND()',     'defaultSort' => ''],
]);

$paginator = new Gazelle\Util\Paginator(REQUESTS_PER_PAGE, (int)($_GET['page'] ?? 1));
if ($header->getOrderBy() === 'random') {
    $search->limit(0, REQUESTS_PER_PAGE, REQUESTS_PER_PAGE);
} else {
    $offset = ($paginator->page() - 1) * REQUESTS_PER_PAGE;
    $search->limit($offset, REQUESTS_PER_PAGE, $offset + REQUESTS_PER_PAGE);
}

$search->execute($header->getOrderBy(), $header->getOrderDir());
$paginator->setTotal($search->total());

echo $Twig->render('request/index.twig', [
    'bookmark_view'   => $bookmarkView,
    'bounty'          => $Viewer->ordinal()->value('request-bounty-vote'),
    'filter_cat'      => $categoryList,
    'encoding_strict' => $encodingStrict,
    'format_strict'   => $formatStrict,
    'media_strict'    => $mediaStrict,
    'header'          => $header,
    'initial'         => $initial,
    'release_types'   => $releaseTypes,
    'search'          => $search,
    'search_text'     => $_GET['search'] ?? null,
    'paginator'       => $paginator,
    'requestor'       => $requestor ?? null,
    'tag_mode'        => $_GET['tag_mode'] ?? 'all',
    'filtering'       => true, // false on artist page
    'show_filled'     => $_GET['show_filled'] ?? null,
    'show_old'        => $_GET['showall'] ?? null,
    'type'            => $_GET['type'] ?? null,
    'user'            => $user,
    'viewer'          => $Viewer,
]);
