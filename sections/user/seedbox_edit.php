<?php

if (!isset($_POST['action'])) {
    $userId = (int)($_GET['userid'] ?? $LoggedUser['ID']);
    if (!$userId) {
        error(404);
    }
    if ($LoggedUser['ID'] != $userId && !check_perms('users_view_ips')) {
        error(403);
    }
} else {
    authorize();
    $userId = (int)$_POST['userid'];
    if (!$userId) {
        error(404);
    }
}

$user = (new Gazelle\Manager\User)->findById($userId);
if (!$user) {
    error(404);
}
$seedbox = new Gazelle\Seedbox($user->id());
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
?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($userId, false, false, false)?> &rsaquo; Seedboxes &rsaquo; Configure</h2>
        <div class="linkbox">
            <a href="user.php?action=seedbox&amp;userid=<?= $userId ?>" class="brackets">Configure</a>
            <a href="user.php?action=seedbox-view&amp;userid=<?= $userId ?>" class="brackets">View</a>
        </div>
    </div>
<?= G::$Twig->render('seedbox/config.twig',[
    'auth' => $LoggedUser['AuthKey'],
    'free' => $seedbox->freeList(),
    'host' => $seedbox->hostList(),
    'userid' => $userId,
]) ?>
</div>
<?php
View::show_footer();
