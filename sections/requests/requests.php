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
$releaseTypes = (new \Gazelle\ReleaseType())->list();
$search->setFormat($_GET['formats'] ?? [], isset($_GET['formats_strict']))
    ->setMedia($_GET['media'] ?? [], isset($_GET['media_strict']))
    ->setEncoding($_GET['bitrates'] ?? [], isset($_GET['bitrate_strict']))
    ->setText($_GET['search'] ?? '')
    ->setTag($_GET['tags'] ?? '', $_GET['tag_mode'] ?? 'all')
    ->setCategory($_GET['filter_cat'] ?? [])
    ->setReleaseType($_GET['releases'] ?? [], $releaseTypes);

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
    'filter_cat'      => $_GET['filter_cat'] ?? [],
    'bitrate_strict'  => $_GET['bitrate_strict'] ?? null,
    'formats_strict'  => $_GET['formats_strict'] ?? null,
    'media_strict'    => $_GET['media_strict'] ?? null,
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
