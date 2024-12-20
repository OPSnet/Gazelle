<?php

$userMan = new Gazelle\Manager\User();
if (!isset($_GET['id'])) {
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_GET['id']);
    if (is_null($user)) {
        error(404);
    }
}
$userId = $user->id();
$ownProfile = $user->id() == $Viewer->id();
if (!$Viewer->permitted('users_view_invites') && !$ownProfile) {
    error(403);
}

$inviteSourceMan = $Viewer->permitted('users_view_invites') || $Viewer->isRecruiter()
    ? new Gazelle\Manager\InviteSource()
    : null;

if ($inviteSourceMan && isset($_GET['edit'])) {
    /**
     * We have:
     *  {
     *      "user-9112": "s-17",
     *      "reason-9112": "https:\/\/whe.re\/user\/4567",
     *  },
     */

    $update = [];
    foreach (array_key_filter_and_map('user-', $_POST) as $userId => $source) {
        if (!isset($update[$userId])) {
            $update[$userId] = ['user_id' => $userId];
        }
        $update[$userId]['source'] = $source === '---' ? 0 : explode('-', $source, 2)[1];
    }
    foreach (array_key_filter_and_map('reason-', $_POST) as $userId => $reason) {
        if (!isset($update[$userId])) {
            $update[$userId] = [];
        }
        $update[$userId]['profile'] = trim($reason);
    }

    /**
     * Now we have:
     *  {
     *      "9112": {
     *          "user_id": 9112,
     *          "source": 17,
     *          "profile": "https:\/\/whe.re\/user\/4567"
     *      }
     *  }
     */

    if ($update) {
        authorize();
        $inviteSourceMan->modifyInviteeSource($user, $update);
    }
}

$heading = new \Gazelle\Util\SortableTableHeader('created', [
    // see Gazelle\User\Invite::page() for these table aliases
    'id'         => ['dbColumn' => 'um.ID',           'defaultSort' => 'desc'],
    'username'   => ['dbColumn' => 'um.Username',     'defaultSort' => 'desc', 'text' => 'Username'],
    'email'      => ['dbColumn' => 'um.Email',        'defaultSort' => 'desc', 'text' => 'Email'],
    'created'    => ['dbColumn' => 'um.created' ,     'defaultSort' => 'desc', 'text' => 'Joined'],
    'lastseen'   => ['dbColumn' => 'ula.last_access', 'defaultSort' => 'desc', 'text' => 'Last Seen'],
    'uploaded'   => ['dbColumn' => 'uls.Uploaded',    'defaultSort' => 'desc', 'text' => 'Uploaded'],
    'downloaded' => ['dbColumn' => 'uls.Downloaded',  'defaultSort' => 'desc', 'text' => 'Downloaded'],
    'ratio'      => ['dbColumn' => '(uls.Uploaded / uls.Downloaded)', 'defaultSort' => 'desc', 'text' => 'Ratio'],
]);

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$paginator->setTotal($user->invite()->total());

echo $Twig->render('user/invited.twig', [
    'edit_source' => $inviteSourceMan && ($_GET['edit'] ?? '') === 'source',
    'heading'     => $heading,
    'invited'     => array_map(
        fn($id) => $userMan->findById($id),
        $user->invite()->page(
            $heading->getOrderBy(), $heading->getOrderDir(), $paginator->limit(), $paginator->offset()
        )
    ),
    'invites_open'      => (new Gazelle\Stats\Users())->newUsersAllowed($user),
    'invite_source'     => $inviteSourceMan,
    'notes'             => new Gazelle\Util\Textarea('notes', '', 60, 4),
    'own_profile'       => $ownProfile,
    'paginator'         => $paginator,
    'user'              => $user,
    'wiki_user_classes' => 4,
    'wiki_ratio_watch'  => 503,
]);
