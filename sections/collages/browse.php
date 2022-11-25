<?php

$userMan = new Gazelle\Manager\User;
$search = (new Gazelle\Search\Collage)->setLookup($_GET['type'] ?? 'name');

if (!empty($_GET['bookmarks'])) {
    $search->setBookmarkView($Viewer);
} elseif (!empty($_GET['cats'])) {
    $search->setCategory(array_keys($_GET['cats']));
}

if (($_GET['action'] ?? '') === 'mine') {
    $search->setUser($Viewer)->setPersonal();
} else {
    if (!empty($_GET['search'])) {
        $search->setWordlist($_GET['search']);
    }

    if (!empty($_GET['tags'])) {
        $tagMan = new Gazelle\Manager\Tag;
        $list = explode(',', $_GET['tags']);
        $taglist = [];
        foreach ($list as $name) {
            $name = $tagMan->sanitize($name);
            if (!empty($name)) {
                $taglist[] = $name;
            }
        }
        if ($taglist) {
            $search->setTaglist($taglist)->setTagAll((bool)($_GET['tags_type'] ?? true));
        }
    }

    if (!empty($_GET['userid'])) {
        $user = $userMan->findById((int)$_GET['userid']);
        if (is_null($user)) {
            error(404);
        }
        if (empty($_GET['contrib'])) {
            if (!$user->propertyVisible($Viewer, 'collages')) {
                error(403);
            }
            $search->setUser($user);
        } else {
            if (!$user->propertyVisible($Viewer, 'collagecontribs')) {
                error(403);
            }
            $search->setContributor($user);
        }
    }
}

$paginator = new Gazelle\Util\Paginator(COLLAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('collage/browse.twig', [
    'input'     => $_GET,
    'page'      => $search->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'personal'  => (new Gazelle\Manager\Collage)->findPersonalByUserId($Viewer->id()),
    'search'    => $search,
    'viewer'    => $Viewer,
]);
