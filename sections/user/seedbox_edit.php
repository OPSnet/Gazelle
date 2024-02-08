<?php

if (!$Viewer->hasAttr('feature-seedbox') && !$Viewer->permitted('users_view_ips')) {
    error(403);
}

if (!isset($_POST['action'])) {
    $userId = (int)($_GET['userid'] ?? $Viewer->id());
} else {
    authorize();
    $userId = (int)$_POST['userid'];
}
$user = (new Gazelle\Manager\User)->findById($userId);
if (!$user) {
    error(404);
}
if ($Viewer->id() != $userId && !$Viewer->permitted('users_view_ips')) {
    error(403);
}

$seedbox = new Gazelle\User\Seedbox($user);

if (isset($_POST['mode'])) {
    switch ($_POST['mode']) {
        case 'update':
            $idList   = array_key_extract_suffix('id-', $_POST);
            $ipList   = array_key_extract_suffix('ip-', $_POST);
            $nameList = array_key_extract_suffix('name-', $_POST);
            $sigList  = array_key_extract_suffix('sig-', $_POST);
            $uaList   = array_key_extract_suffix('ua-', $_POST);
            if (count($idList) != count($ipList)) {
                error("id/ip mismatch");
            } elseif (count($idList) != count($nameList)) {
                error("id/name mismatch");
            } elseif (count($idList) != count($sigList)) {
                error("id/sig mismatch");
            } elseif (count($idList) != count($uaList)) {
                error("id/ua mismatch");
            }
            $update = [];
            for ($i = 1, $end = count($nameList); $i <= $end; ++$i) {
                if ($sigList[$i] != $seedbox->signature($ipList[$i], $uaList[$i])) {
                    error("ip/ua signature failed");
                }
                $update[] = [
                    'id'   => $idList[$i],
                    'name' => $nameList[$i],
                    'ipv4' => $ipList[$i],
                    'ua'   => $uaList[$i],
                ];
            }
            $seedbox->updateNames($update);
            break;
        case 'remove':
            $remove = [];
            foreach (array_key_extract_suffix('rm-', $_POST) as $id) {
                if (isset($_POST["rmid-$id"])) {
                    $remove[] = $_POST["rmid-$id"];
                }
            }
            $seedbox->removeNames($remove);
            break;
        default:
            error(403);
    }
}

echo $Twig->render('seedbox/config.twig', [
    'auth' => $Viewer->auth(),
    'free' => $seedbox->freeList(),
    'host' => $seedbox->hostList(),
    'user' => $user,
]);
