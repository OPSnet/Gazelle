<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_login_watch', 'admin_manage_ipbans')) {
    Error403::error();
}
$nrBan   = 0;
$nrClear = 0;

if ($_POST) {
    authorize();
    $canBan     = $Viewer->permitted('admin_manage_ipbans');
    $banManager = new Manager\Ban();
    $reason     = $_REQUEST['reason'] ?? "Banned by {$Viewer->username()} from login watch";
    $ipList     = [];
    foreach (array_key_filter_and_map('admin-', $_POST, cast: false) as $ip => $val) {
        $ip = str_replace('_', '.', (string)$ip);
        $ipList[] = $ip;
        if ($canBan && $val === 'ban') {
            $banManager->create($ip, $reason, $Viewer);
            $nrBan++;
        } elseif ($val === 'clear') {
            new LoginWatch($ip)->unblock();
            $nrClear++;
        }
    }
    if ($ipList) {
        Util\Irc::sendMessage(
            IRC_CHAN_STATUS,
            'login watch '
                . ($nrBan ? 'ip ban set' : 'cleared')
                . " by {$Viewer->username()} on "
                . implode(', ', $ipList)
        );
    }
}

$watchMan  = new Manager\LoginWatch();
$paginator = new Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($watchMan->activeTotal());

echo $Twig->render('admin/login-watch.twig', [
    'nr_ban'    => $nrBan,
    'nr_clear'  => $nrClear,
    'paginator' => $paginator,
    'viewer'    => $Viewer,
    'watch_man' => $watchMan,
]);
