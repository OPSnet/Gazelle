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

$navItems  = $userMan->forumnavItemList();

$profile = [
    0 => [
        'title' => $user->infoTitle(),
        'ta'    => new Gazelle\Util\Textarea('info', $user->infoProfile(), 42, 8),
    ]
];

$enabledReward = $user->enabledDonorRewards();
$profileReward = $user->profileDonorRewards();
foreach (range(1, 4) as $level) {
    if (!$enabledReward["HasProfileInfo$level"]) {
        $profile[$level] = [
            'enabled'  => false,
        ];
    } else {
        $profile[$level] = [
            'enabled' => true,
            'title'   => display_str($profileReward["ProfileInfoTitle$level"]),
            'ta'      => new Gazelle\Util\Textarea("profile_info_$level", $profileReward["ProfileInfo$level"] ?? '', 42, 8),
        ];
    }
}

echo $Twig->render('user/setting.twig', [
    'enabled_reward'   => $enabledReward,
    'js'               => (new Gazelle\Util\Validator)->generateJS('userform'),
    'lastfm_username'  => (new Gazelle\Util\LastFM)->username($UserID),
    'nav_items'        => $navItems,
    'nav_items_user'   => $user->forumNavList() ?: array_keys(array_filter($navItems, fn($item) => $item['initial'])),
    'notify_config'    => (new Gazelle\User\Notification($user))->config(),
    'profile'          => $profile,
    'release_order'    => $user->releaseOrder((new Gazelle\ReleaseType)->extendedList()),
    'stylesheet'       => new Gazelle\User\Stylesheet($user),
    'stylesheets'      => (new Gazelle\Manager\Stylesheet)->list(),
    'user'             => $user,
    'viewer'           => $Viewer,
]);
