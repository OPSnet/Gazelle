<?php

$userMan = new Gazelle\Manager\User;

$user = $userMan->findById(($_REQUEST['id'] ?? '') === 'me' ? $Viewer->id() : (int)($_REQUEST['id'] ?? 0));
if (is_null($user)) {
    error(404);
}
$UserID = $user->id();
if ($UserID != $Viewer->id() && !$Viewer->permitted('users_edit_profiles')) {
    error(403);
}

$donor    = new Gazelle\User\Donor($user);
$profile  = [
    'title' => $user->profileTitle(),
    'info'  => new Gazelle\Util\Textarea('info', $user->profileInfo(), 42, 8),
];
foreach (range(1, 4) as $level) {
    if ($donor->profileInfo($level) !== false) {
        $profile[$level] = [
            'title' => $donor->profileTitle($level),
            'info'  => new Gazelle\Util\Textarea("profile_info_$level", $donor->profileInfo($level) ?? '', 42, 8),
        ];
    }
}
$navItems = $userMan->forumNavItemList();

echo $Twig->render('user/setting.twig', [
    'donor'           => $donor,
    'js'              => (new Gazelle\Util\Validator)->generateJS('userform'),
    'lastfm_username' => (new Gazelle\Util\LastFM)->username($user),
    'nav_items'       => $navItems,
    'nav_items_user'  => $user->forumNavList() ?: array_keys(array_filter($navItems, fn($item) => $item['initial'])),
    'notify_config'   => (new Gazelle\User\Notification($user))->config(),
    'profile'         => $profile,
    'release_order'   => $user->releaseOrder((new Gazelle\ReleaseType)->extendedList()),
    'stylesheet'      => new Gazelle\User\Stylesheet($user),
    'stylesheets'     => (new Gazelle\Manager\Stylesheet)->list(),
    'user'            => $user,
    'viewer'          => $Viewer,
]);
