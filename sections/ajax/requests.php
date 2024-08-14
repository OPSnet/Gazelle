<?php
/** @phpstan-var \Gazelle\User $Viewer */

$search = new Gazelle\Search\Request(new Gazelle\Manager\Request());

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['userid'])) {
    $user = null;
} else {
    $user = $userMan->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        json_die("failure");
    }
}

$type = $_GET['type'] ?? '';
switch ($type) {
    case 'created':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                json_die("failure");
            }
            $Title = "Requests created by " . $user->username();
            $search->setCreator($user);
        } else {
            $Title = 'My requests';
            $search->setCreator($Viewer);
        }
        break;
    case 'voted':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                json_die("failure");
            }
            $Title = "Requests voted for by " . $user->username();
            $search->setVoter($user);
        } else {
            $Title = 'Requests you have voted on';
            $search->setVoter($Viewer);
        }
        break;
    case 'filled':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                json_die("failure");
            }
            $Title = "Requests filled by " . $user->username();
            $search->setFiller($user);
        } else {
            $Title = 'Requests you have filled';
            $search->setFiller($Viewer);
        }
        break;
    case 'bookmarks':
        $Title = 'Your bookmarked requests';
        if (is_null($user)) {
            error("No user id given");
        }
        $search->setBookmarker($user);
        $BookmarkView = true;
        break;
    default:
        $Title = 'Requests';
        if (!isset($_GET['showall'])) {
            $search->setVisible(true);
        }
        break;
}

$strict = true;
$search->setFormat($_GET['formats'] ?? [], $strict)
    ->setMedia($_GET['media'] ?? [], $strict)
    ->setEncoding($_GET['bitrates'] ?? [], $strict)
    ->setText($_GET['search'] ?? '')
    ->setTag(
        $_GET['tags'] ?? '',
        match ($_GET['tag_type'] ?? '1') {
            '1'     => 'all',
            default => 'any',
        },
    )
    ->setCategory($_GET['filter_cat'] ?? [])
    ->setReleaseType($_GET['releases'] ?? [], (new \Gazelle\ReleaseType())->list());

if (!isset($_GET['show_filled'])) {
    $search->showUnfilled();
}

if (isset($_GET['year'])) {
    $search->setYear((int)$_GET['year']);
}

if (isset($_GET['requestor'])) {
    $requestor = (int)$_GET['requestor'];
    if ($requestor) {
        $search->setRequestor($requestor);
    } else {
        error(404);
    }
}

$paginator = new Gazelle\Util\Paginator(REQUESTS_PER_PAGE, (int)($_GET['page'] ?? 1));
if ($type === 'random') {
    $search->limit(0, REQUESTS_PER_PAGE, REQUESTS_PER_PAGE);
} else {
    $offset = ($paginator->page() - 1) * REQUESTS_PER_PAGE;
    $search->limit($offset, REQUESTS_PER_PAGE, $offset + REQUESTS_PER_PAGE);
}

$search->execute(
    match ($type) {
        'year'     => 'year',
        'votes'    => 'votes',
        'bounty'   => 'bounty',
        'filled'   => 'timefilled',
        'lastvote' => 'lastvote',
        'random'   => 'RAND()',
        default    => 'timeadded',
    },
    ($_GET['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc'
);
$paginator->setTotal($search->total());

echo (new Gazelle\Json\Requests($search, $paginator->page(), $userMan))
    ->setVersion(2)
    ->response();
