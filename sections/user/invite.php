<?php

if (!isset($_REQUEST['id'])) {
    $user = $Viewer;
} else {
    $user = (new Gazelle\Manager\User)->findById((int)$_REQUEST['id']);
    if (is_null($user)) {
        error(404);
    }
}
$userId = $user->id();
$ownProfile = $user->id() == $Viewer->id();
if (!($Viewer->permitted('users_view_invites') || $ownProfile)) {
    error(403);
}

$userSourceRaw = array_filter($_POST, fn($x) => preg_match('/^user-\d+$/', $x), ARRAY_FILTER_USE_KEY);
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

echo $Twig->render('user/invited.twig', [
    'edit_source'       => ($_GET['edit'] ?? '') === 'source',
    'heading'           => $heading,
    'invited'           => $user->inviteList($heading->getOrderBy(), $heading->getOrderDir()),
    'inviter_config'    => $invSourceMan->inviterConfigurationActive($userId),
    'invites_open'      => (new Gazelle\Stats\Users)->newUsersAllowed($user),
    'invite_source'     => $invSourceMan->userSource($userId),
    'own_profile'       => $ownProfile,
    'user'              => $user,
    'user_source'       => $invSourceMan->userSource($userId),
    'wiki_user_classes' => 4,
    'wiki_ratio_watch'  => 503,
]);
