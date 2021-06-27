<?php

$userMan = new Gazelle\Manager\User;
$user = empty($_REQUEST['userid']) ? $Viewer : $userMan->findById((int)$_REQUEST['userid']);
if (is_null($user)) {
    error(404);
}
$userId = $user->id();
$ownProfile = $user->id() == $Viewer->id();
if (!($Viewer->permitted('users_view_invites') || ($ownProfile && $Viewer->canPurchaseInvite()))) {
    error(403);
}

$userSourceRaw = array_filter($_POST, function ($x) { return preg_match('/^user-\d+$/', $x); }, ARRAY_FILTER_USE_KEY);
$userSource = [];
foreach ($userSourceRaw as $fieldName => $fieldValue) {
    if (preg_match('/^user-(\d+)$/', $fieldName, $userMatch) && preg_match('/^s-(\d+)$/', $fieldValue, $sourceMatch)) {
        $userSource[$userMatch[1]] = (int)$sourceMatch[1];
    }
}

$invSourceMan = new Gazelle\Manager\InviteSource;
if (count($userSource)) {
    $invSourceMan->modifyUserSource($userId, $userSource);
}

$heading = new \Gazelle\Util\SortableTableHeader('joined', [
    // see Gazelle\User::inviteList() for these table aliases
    'id'         => ['dbColumn' => 'um.ID',           'defaultSort' => 'desc'],
    'username'   => ['dbColumn' => 'um.Username',     'defaultSort' => 'desc', 'text' => 'Username'],
    'email'      => ['dbColumn' => 'um.Email',        'defaultSort' => 'desc', 'text' => 'Email'],
    'joined'     => ['dbColumn' => 'ui.JoinDate',     'defaultSort' => 'desc', 'text' => 'Joined'],
    'lastseen'   => ['dbColumn' => 'ula.last_access', 'defaultSort' => 'desc', 'text' => 'Last Seen'],
    'uploaded'   => ['dbColumn' => 'uls.Uploaded',    'defaultSort' => 'desc', 'text' => 'Uploaded'],
    'downloaded' => ['dbColumn' => 'uls.Downloaded',  'defaultSort' => 'desc', 'text' => 'Downloaded'],
    'ratio'      => ['dbColumn' => '(uls.Uploaded / uls.Downloaded)', 'defaultSort' => 'desc', 'text' => 'Ratio'],
]);

View::show_header('Invites');

echo $Twig->render('user/invited.twig', [
    'auth'           => $user->auth(),
    'edit_source'    => ($_GET['edit'] ?? '') === 'source',
    'heading'        => $heading,
    'invited'        => $user->inviteList($heading->getOrderBy(), $heading->getOrderDir()),
    'inviter_config' => $invSourceMan->inviterConfigurationActive($userId),
    'invites_open'   => $userMan->newUsersAllowed() || $user->permitted('site_can_invite_always'),
    'invite_source'  => $invSourceMan->userSource($userId),
    'own_profile'    => $ownProfile,
    'user'           => $user,
    'user_source'    => $invSourceMan->userSource($userId),
    'view_pool'      => $user->permitted('users_view_invites'),
    'wiki_article'   => 116,
]);
View::show_footer();
