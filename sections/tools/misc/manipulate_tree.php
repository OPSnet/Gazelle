<?php
// Props to Leto of StC.
if (!check_perms('users_view_invites') && !check_perms('users_disable_users') && !check_perms('users_edit_invites') && !check_perms('users_disable_any')) {
    error(403);
}

$doComment = false;
$doDisable = false;
$doInvites = false;
$message = null;

if ($_POST['id']) {
    authorize();
    $action = $_POST['perform'] ?? '';
    $doComment = $action === 'comment';
    $doDisable = $action === 'disable';
    $doInvites = $action === 'invites';
    if (!($doComment || $doDisable || $doInvites)) {
        error("No maniplation action specified");
    }
    if (!$_POST['comment']) {
        error('Please enter a comment to add to the users affected.');
    }
    $userId = (int)$_POST['id'];
    if (!$userId) {
        error(404);
    }
    $user = new Gazelle\User($userId);
    $username = $user->username();

    $inviteTree = new Gazelle\InviteTree($userId);
    if (!$inviteTree->treeId()) {
        $message = "No invite tree exists for $username";
    } else {
        $userMan = new Gazelle\Manager\User;
        $inviteeList = $inviteTree->inviteeList();
        $inviteeCount = count($inviteeList);
        if (!$inviteeCount) {
            $message = "No invitees for $username";
        } else {
            if ($doComment) {
                $message = "Commented on";
                $comment = "comment";
            } elseif ($doDisable) {
                $message = "Banned";
                $comment = "disable";
            } elseif ($doInvites) {
                $message = "Removed invite privileges from";
                $comment = "invites removed";
            }
            $message .= " entire tree ({$inviteeCount} user" . plural($inviteeCount) . ')';
            $comment = date('Y-m-d H:i:s') . " - {$_POST['comment']}\nInvite Tree $comment on $username by {$LoggedUser['Username']}";
            foreach ($inviteeList as $inviteeId) {
                $user = $userMan->findById($inviteeId);
                if ($doComment) {
                    $user->addStaffNote($comment)->modify();
                } elseif ($doDisable) {
                    $userMan->disableUserList([$inviteeId], $comment, Gazelle\Manager\User::DISABLE_TREEBAN);
                } elseif ($doInvites) {
                    $user->addStaffNote($comment)->modify();
                    $userMan->disableInvites($inviteeId);
                }
            }
        }
    }
}

View::show_header('Manipulate Invite Tree');
echo $Twig->render('user/invite-tree-bulkedit.twig', [
    'auth'    => $LoggedUser['AuthKey'],
    'message' => $message,
]);
View::show_footer();
