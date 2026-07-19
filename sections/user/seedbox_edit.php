<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->hasAttr('feature-seedbox') && !$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

if (!isset($_POST['action'])) {
    $userId = (int)($_GET['userid'] ?? $Viewer->id);
} else {
    authorize();
    $userId = (int)$_POST['userid'];
}
$user = new Manager\User()->findById($userId);
if (!$user) {
    Error404::error();
}
if ($Viewer->id != $userId && !$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$seedbox = new User\Seedbox($user);

if (isset($_POST['mode'])) {
    switch ($_POST['mode']) {
        case 'update':
            $idList   = array_key_filter_and_map('id-', $_POST);
            $ipList   = array_key_filter_and_map('ip-', $_POST);
            $nameList = array_key_filter_and_map('name-', $_POST);
            $sigList  = array_key_filter_and_map('sig-', $_POST);
            $uaList   = array_key_filter_and_map('ua-', $_POST);
            if (count($idList) != count($ipList)) {
                Error400::error("id/ip mismatch");
            } elseif (count($idList) != count($nameList)) {
                Error400::error("id/name mismatch");
            } elseif (count($idList) != count($sigList)) {
                Error400::error("id/sig mismatch");
            } elseif (count($idList) != count($uaList)) {
                Error400::error("id/ua mismatch");
            }
            $update = [];
            foreach (array_keys($idList) as $i) {
                if ($sigList[$i] != signature("{$ipList[$i]}/{$uaList[$i]}", SEEDBOX_SALT)) {
                    Error400::error("ip/ua signature failed");
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
            Error403::error();
    }
}

echo $Twig->render('seedbox/config.twig', [
    'seedbox' => $seedbox,
    'user'    => $user,
    'viewer'  => $Viewer,
]);
