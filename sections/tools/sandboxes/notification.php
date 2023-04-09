<?php

if (!$Viewer->permitted('admin_view_notifications')) {
    error(403);
}

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_POST['torrentid'] ?? 0));

$notifiedId   = null;
$result       = [];
$notification = null;
if ($torrent) {
    $notification = new \Gazelle\Notification\Upload($torrent);

    $result = $notification->userFilterList();
    if (isset($_POST['notifiedid'])) {
        $notified = (new Gazelle\Manager\User)->find(trim($_POST['notifiedid']));
        if ($notified) {
            $notifiedId = $notified->id();
            $result = array_filter($result, fn($r) => $r['user_id'] === $notifiedId);
        }
    }

    foreach ($result as &$r) {
        $r['filter'] = new Gazelle\NotificationFilter($r['filter_id']);
    }
    unset($r);
}

echo $Twig->render('admin/notification-sandbox.twig', [
    'notification' => $notification,
    'notified_id'  => $notifiedId,
    'result'       => $result,
    'torrent'      => $torrent,
]);
