<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->hasAttr('feature-seedbox') && !$Viewer->permitted('users_view_ips')) {
    error(403);
}

if (!isset($_POST['action'])) {
    $userId = (int)($_GET['userid'] ?? $Viewer->id());
} else {
    authorize();
    $userId = (int)$_POST['userid'];
}
$user = (new Gazelle\Manager\User())->findById($userId);
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
            $idList   = array_key_filter_and_map('id-', $_POST);
            $ipList   = array_key_filter_and_map('ip-', $_POST);
            $nameList = array_key_filter_and_map('name-', $_POST);
            $sigList  = array_key_filter_and_map('sig-', $_POST);
            $uaList   = array_key_filter_and_map('ua-', $_POST);
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
            foreach (array_keys($idList) as $i) {
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
            $remove = array_key_extract_suffix('rm-', $_POST, false);
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
