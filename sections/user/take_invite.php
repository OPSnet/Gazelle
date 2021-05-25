<?php

use Gazelle\Util\Mail;

authorize();

/**
 * Hello there. If you are refactoring this code, please note that this functionality also sort of exists in /classes/referral.class.php
 * Super sorry for doing that, but this is totally not reusable.
 */

$user = new Gazelle\User($LoggedUser['ID']);
// Can the member issue an invite?
if (!$user->canInvite()) {
    error(403);
}
// Can the site allow an invite to be spent?
if (!((new Gazelle\Manager\User)->newUsersAllowed() || check_perms('site_can_invite_always'))) {
    error(403);
}

//MultiInvite
$Email = $_POST['email'];
if (strpos($Email, '|') !== false && check_perms('site_send_unlimited_invites')) {
    $Emails = explode('|', $Email);
} else {
    $Emails = [$Email];
}

foreach ($Emails as $CurEmail) {
    $CurEmail = trim($CurEmail);
    if (!preg_match(EMAIL_REGEXP, $CurEmail)) {
        if (count($Emails) > 1) {
            continue;
        } else {
            error('Invalid email.');
        }
    }
    $DB->prepared_query("
        SELECT 1
        FROM invites
        WHERE InviterID = ?
            AND Email = ?
        ", $LoggedUser['ID'], $CurEmail
    );
    if ($DB->has_results()) {
        error('You already have a pending invite to that address!');
    }

    $InviteKey = randomString();
    $DB->begin_transaction();
    $DB->prepared_query("
        INSERT INTO invites
               (InviterID, InviteKey, Email, Reason, Expires)
        VALUES (?,         ?,         ?,     ?,      now() + INTERVAL 3 DAY)
        ", $LoggedUser['ID'], $InviteKey, $CurEmail, (check_perms('users_invite_notes') ? trim($_POST['reason'] ?? '') : '')
    );
    if (!check_perms('site_send_unlimited_invites')) {
        $DB->prepared_query("
            UPDATE users_main SET
                Invites = GREATEST(Invites, 1) - 1
            WHERE ID = ?
            ", $LoggedUser['ID']
        );
        $user->flush();
    }
    $DB->commit();

    (new Mail)->send($CurEmail, 'You have been invited to ' . SITE_NAME,
        $Twig->render('email/invite-member.twig', [
            'email'    => $CurEmail,
            'key'      => $InviteKey,
            'username' => $LoggedUser['Username'],
        ])
    );
}

header('Location: user.php?action=invite');
