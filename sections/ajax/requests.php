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
switch ($_GET['type'] ?? null) {
    case 'created':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                json_die("hidden by paranoia");
            }
            $search->setCreator($user);
        } else {
            $search->setCreator($Viewer);
        }
        break;
    case 'voted':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsvoted_list')) {
                json_die("hidden by paranoia");
            }
            $search->setVoter($user);
        } else {
            $search->setVoter($Viewer);
        }
        break;
    case 'filled':
        if ($user) {
            if (!$user->propertyVisible($Viewer, 'requestsfilled_list')) {
                json_die("hidden by paranoia");
            }
            $search->setFiller($user);
        } else {
            $search->setFiller($Viewer);
        }
        break;
    case 'bookmarks':
        $search->setBookmarker($Viewer);
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
