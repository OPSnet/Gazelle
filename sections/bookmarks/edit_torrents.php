<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        Error403::error();
    }
    $user = new Manager\User()->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        Error404::error();
    }
}

$tgMan = new Manager\TGroup();

$list = [];
foreach (new User\Bookmark($user)->bookmarkList(Enum\BookmarkType::tgroup) as $info) {
    $tgroup = $tgMan->findById($info['tgroup_id']);
    if (is_null($tgroup)) {
        continue;
    }
    $list[] = [
        'created'     => $info['created'],
        'link_artist' => $tgroup->artistRole()?->link() ?? '&mdash;',
        'link_tgroup' => sprintf(
            '<a href="%s" title="View torrent group" class="tooltip" dir="ltr">%s</a>',
            $tgroup->url(),
            display_str($tgroup->name())
        ),
        'sequence'    => $info['sequence'],
        'showcase'    => $tgroup->isShowcase(),
        'tgroup_id'   => $info['tgroup_id'],
        'year'        => $tgroup->year(),
    ];
}

echo $Twig->render('bookmark/body.twig', [
    'edit_type' => $_GET['type'] ?? 'torrents',
    'list'      => $list,
    'user'      => $user,
    'viewer'    => $Viewer,
]);
