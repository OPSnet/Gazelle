<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();
$search = new Search\Collage()->setLookup($_GET['type'] ?? 'name');

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
        $tagMan = new Manager\Tag();
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
            Error404::error();
        }
        if (empty($_GET['contrib'])) {
            if (!$user->propertyVisible($Viewer, 'collages')) {
                Error403::error();
            }
            $search->setUser($user);
        } else {
            if (!$user->propertyVisible($Viewer, 'collagecontribs')) {
                Error403::error();
            }
            $search->setContributor($user);
        }
    }
}

if ($Viewer->hasAttr('show-all-tags')) {
    $search->disableFilter();
}

$paginator = new Util\Paginator(COLLAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('collage/browse.twig', [
    'input'     => $_GET,
    'page'      => $search->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'personal'  => new Manager\Collage()->findPersonalByUser($Viewer),
    'search'    => $search,
    'viewer'    => $Viewer,
]);
