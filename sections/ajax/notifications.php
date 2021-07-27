<?php
if (!$Viewer->permitted('site_torrents_notify')) {
    json_die("failure");
}

$notifier = new Gazelle\Notification\Torrent($Viewer->id());
if ((int)$_GET['filterid']) {
    $notifier->setFilter((int)$_GET['filterid']);
}
$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($notifier->total());
$list = $notifier->unreadList($paginator->limit(), $paginator->offset());
$tgroupList = Torrents::get_groups(array_column($list, 'groupId'));

$new = 0;
$notification = [];
foreach ($list as $result) {
    $torrentId = (int)$result['torrentId'];
    $tgroup    = $tgroupList[$result['groupId']];
    $info      = $tgroup['Torrents'][$torrentId];
    if ($result['unread'] == 1) {
        $new++;
    }

    $notification[] = [
        'torrentId'        => $torrentId,
        'groupId'          => (int)$tgroup['ID'],
        'groupName'        => $tgroup['Name'],
        'groupCategoryId'  => (int)$tgroup['CategoryID'],
        'wikiImage'        => $tgroup['WikiImage'],
        'torrentTags'      => $tgroup['TagList'],
        'size'             => (float)$info['Size'],
        'fileCount'        => (int)$info['FileCount'],
        'format'           => $info['Format'],
        'encoding'         => $info['Encoding'],
        'media'            => $info['Media'],
        'scene'            => $info['Scene'] == 1,
        'groupYear'        => (int)$tgroup['Year'],
        'remasterYear'     => (int)$info['RemasterYear'],
        'remasterTitle'    => $info['RemasterTitle'],
        'snatched'         => (int)$info['Snatched'],
        'seeders'          => (int)$info['Seeders'],
        'leechers'         => (int)$info['Leechers'],
        'notificationTime' => $info['Time'],
        'hasLog'           => $info['HasLog'] == 1,
        'hasCue'           => $info['HasCue'] == 1,
        'logScore'         => (float)$info['LogScore'],
        'freeTorrent'      => $info['FreeTorrent'] == 1,
        'logInDb'          => $info['HasLog'] == 1,
        'unread'           => $result['unread'] == 1,
        'filter'           => $result['label'],
    ];
}

json_print("success", [
    'currentPages' => $paginator->page(),
    'pages'        => (int)ceil($paginator->total() / ITEMS_PER_PAGE),
    'numNew'       => $new,
    'results'      => $notification,
]);
