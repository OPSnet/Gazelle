<?php

if (!$Viewer->permitted('admin_freeleech')) {
    error(403);
}

// use a while to early-exit in case of an error
while (isset($_POST['torrents'])) {

    $reason = $_POST['reason'];
    if (!in_array($reason, ['0', '1', '2', '3'])) {
        $message = "Invalid or freeleech reason";
        break;
    }

    $leechType = $_POST['leech_type'];
    if (!in_array($leechType, ['0', '1', '2'])) {
        $message = "Invalid freeleech type";
        break;
    }

    $db = Gazelle\DB::DB();
    $tgMan = new Gazelle\Manager\TGroup;
    $GroupIDs = [];
    $Elements = explode("\r\n", $_POST['torrents']);
    foreach ($Elements as $Element) {
        if (preg_match(TGROUP_REGEXP, $Element, $match)) {
            $tgroup = $tgMan->findById((int)$match['id']);
            if ($tgroup) {
                $GroupIDs[] = $tgroup->id();
            }
        } elseif (preg_match('/\/collages\.php\?.*?\bid=(?P<id>\d+)$/', $Element, $match)) {
            $collage = (new Gazelle\Manager\Collage)->findById((int)$match['id']);
            if ($collage) {
                $db->prepared_query("
                    SELECT GroupID FROM collages_torrents WHERE CollageID = ?
                    ", $collage->id()
                );
                array_push($GroupIDs, ...$db->collect('GroupID', false));
            }
        }
    }
    if (empty($GroupIDs)) {
        $message = "There were no groups found in provided links";
        break;
    }

    $db->prepared_query("
        SELECT DISTINCT ID FROM torrents WHERE GroupID IN (" . placeholders($GroupIDs) . ")",
        ...$GroupIDs
    );
    $torrentIds = $db->collect(0, false);
    if (empty($torrentIds)) {
        $message = "There were no torrents in the groups found in the provided links";
        break;
    }

    $affected = (new Gazelle\Manager\Torrent)
        ->setFreeleech($Viewer, $torrentIds, $reason, $leechType, isset($_POST['all']), isset($_POST['limit']));
    $message = "Done! ($affected changed)";
    break;
}

echo $Twig->render('admin/freeleech.twig', [
    'all'         => $_POST['all'] ?? 0,
    'error'       => $message,
    'leech_type'  => $_POST['leech_type'] ?? '0',
    'limit'       => $_POST['limit'] ?? 1,
    'list'        => new Gazelle\Util\Textarea('torrents', $_POST['torrents'] ?? ''),
    'reason'      => $_POST['reason'] ?? '0',
    'reason_list' => ['0' => 'N/A', '1' => 'Staff Pick', '2' => 'Perma-FL', '3' => 'Showcase'],
    'viewer'      => $Viewer,
]);
