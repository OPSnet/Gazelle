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

$seedbox = new Gazelle\Seedbox($userId);
View::show_header($user->username() . ' &rsaquo; Seedboxes');

if (isset($_POST['mode'])) {
    switch ($_POST['mode']) {
        case 'update':
            $id   = array_filter($_POST, function ($x) { return preg_match('/^id-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            $ip   = array_filter($_POST, function ($x) { return preg_match('/^ip-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            $name = array_filter($_POST, function ($x) { return preg_match('/^name-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            $sig  = array_filter($_POST, function ($x) { return preg_match('/^sig-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            $ua   = array_filter($_POST, function ($x) { return preg_match('/^ua-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            if (count($id) != count($ip)) {
                error("id/ip mismatch");
            } elseif (count($id) != count($name)) {
                error("id/name mismatch");
            } elseif (count($id) != count($sig)) {
                error("id/sig mismatch");
            } elseif (count($id) != count($ua)) {
                error("id/ua mismatch");
            }
            $update = [];
            for ($i = 1, $end = count($name); $i <= $end; ++$i) {
                if ($sig["sig-$i"] != $seedbox->signature($ip["ip-$i"], $ua["ua-$i"])) {
                    error("ip/ua signature failed");
                }
                $update[] = [
                    'id'   => $id["id-$i"],
                    'name' => $name["name-$i"],
                    'ipv4' => $ip["ip-$i"],
                    'ua'   => $ua["ua-$i"],
                ];
            }
            $seedbox->updateNames($update);
            break;
        case 'remove':
            $rm = array_map(
                function($x) {return explode('-', $x)[1];},
                array_keys(
                    array_filter($_POST, function ($x) {return preg_match('/^rm-\d+$/', $x);}, ARRAY_FILTER_USE_KEY)
                )
            );
            $rmid = array_filter($_POST, function ($x) {return preg_match('/^rmid-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
            $remove = [];
            foreach ($rm as $id) {
                if (isset($rmid["rmid-$id"])) {
                    $remove[] = $rmid["rmid-$id"];
                }
            }
            $seedbox->removeNames($remove);
            break;
        default:
            error(403);
    }
}

echo $Twig->render('seedbox/config.twig',[
    'auth'    => $Viewer->auth(),
    'free'    => $seedbox->freeList(),
    'host'    => $seedbox->hostList(),
    'user_id' => $userId,
]);

View::show_footer();
