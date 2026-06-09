<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();

$user = $userMan->findById(($_REQUEST['id'] ?? '') === 'me' ? $Viewer->id : (int)($_REQUEST['id'] ?? 0));
if (is_null($user)) {
    Error404::error();
}
$UserID = $user->id;
if ($UserID != $Viewer->id && !$Viewer->permitted('users_edit_profiles')) {
    Error403::error();
}

$donor    = new User\Donor($user);
$profile  = [
    'title' => $user->profileTitle(),
    'info'  => new Util\Textarea('info', $user->profileInfo(), 42, 8),
];
foreach (range(1, 4) as $level) {
    if ($donor->profileInfo($level) !== false) {
        $profile[$level] = [
            'title' => $donor->profileTitle($level),
            'info'  => new Util\Textarea("profile_info_$level", $donor->profileInfo($level) ?? '', 42, 8),
        ];
    }
}
$navList = new Manager\UserNavigation()->fullList();
$pushToken = new User\Notification($user)->pushToken();

echo $Twig->render('user/setting.twig', [
    'donor'           => $donor,
    'lastfm_username' => new Util\LastFM()->username($user),
    'nav_items'       => $navList,
    'nav_items_user'  => $user->navigationList(),
    'notify_config'   => new User\Notification($user)->config(),
    'push_topic'      => $pushToken,
    'profile'         => $profile,
    'release_order'   => $user->releaseOrder(new ReleaseType()->extendedList()),
    'stylesheet'      => new User\Stylesheet($user),
    'stylesheets'     => new Manager\Stylesheet()->list(),
    'user'            => $user,
    'viewer'          => $Viewer,
]);
