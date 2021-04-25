<?php

$userMan = new Gazelle\Manager\User;

if (isset($_GET['userid'])) {
    if (!check_perms('users_view_invites')) {
        error(403);
    }
    $UserID = (int)$_GET['userid'];
} else {
    $UserID = $LoggedUser['ID'];
}
$user = $userMan->findById($UserID);
if (is_null($user)) {
    error(404);
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
    'auth'         => $LoggedUser['AuthKey'],
    'heading'      => $heading,
    'invited'      => $user->inviteList($heading->getOrderBy(), $heading->getOrderDir()),
    'invites_open' => $userMan->newUsersAllowed() || $user->permitted('site_can_invite_always'),
    'own_profile'  => $user->id() == $LoggedUser['ID'],
    'user'         => $user,
    'view_pool'    => check_perms('users_view_invites'),
    'wiki_article' => 116,
]);
View::show_footer();
