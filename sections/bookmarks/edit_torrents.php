<?php

if (empty($_GET['userid'])) {
    $user = $Viewer;
} else {
    if (!$Viewer->permitted('users_override_paranoia')) {
        error(403);
    }
    $user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
    if (is_null($user)) {
        error(404);
    }
}

$tgMan = new Gazelle\Manager\TGroup;

$list = [];
foreach ((new Gazelle\User\Bookmark($user))->tgroupBookmarkList() as $info) {
    $tgroup = $tgMan->findById($info['tgroup_id']);
    if (is_null($tgroup)) {
        continue;
    }
    $list[] = [
        'created'     => $info['created'],
        'link_artist' => $tgroup->artistHtml(),
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
