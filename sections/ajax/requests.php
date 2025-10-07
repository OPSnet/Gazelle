<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!isset($_GET['userid'])) {
    $user = null;
} else {
    $user = new Manager\User()->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        json_die("failure");
    }
}

$search = new Search\Request();
$type   = $_GET['type'] ?? '';
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
        if (is_null($user)) {
            json_die("No user id given");
        }
        $search->setBookmarker($user);
        $Title = 'Your bookmarked requests';
        $BookmarkView = true;
        break;
    default:
        $Title = 'Requests';
        break;
}

$strict = true;
$search->setFormat($_GET['formats'] ?? [], $strict)
    ->setMedia($_GET['media'] ?? [], $strict)
    ->setEncoding($_GET['bitrates'] ?? [], $strict)
    ->setSearch($_GET['search'] ?? '')
    ->setTag(
        trim($_GET['tags'] ?? ''),
        match ($_GET['tag_type'] ?? '1') {
            '1'     => Enum\SearchTag::all,
            default => Enum\SearchTag::any,
        },
    )
    ->setCategory($_GET['filter_cat'] ?? [])
    ->setReleaseType($_GET['releases'] ?? []);

if (isset($_GET['show_filled'])) {
    $search->showFilled();
}

if (isset($_GET['year'])) {
    $search->setYear((int)$_GET['year']);
}

$paginator = new Util\Paginator(REQUESTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo new Json\Requests($search, $paginator->page())
    ->setVersion(2)
    ->response();
