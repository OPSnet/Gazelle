<?php

use Gazelle\Util\Mail;

authorize();

/**
 * Hello there. If you are refactoring this code, please note that this functionality also sort of exists in /classes/referral.class.php
 * Super sorry for doing that, but this is totally not reusable.
 */

$Viewer = new Gazelle\User($LoggedUser['ID']);
// Can the member issue an invite?
if (!$Viewer->canInvite()) {
    error(403);
}
// Can the site allow an invite to be spent?
if (!((new Gazelle\Manager\User)->newUsersAllowed() || $Viewer->permitted('site_can_invite_always'))) {
    error(403);
}

if (!isset($_POST['agreement'])) {
    error("You must agree to the conditions for sending invitations.");
}

$email = trim($_POST['email']);
if (!preg_match(EMAIL_REGEXP, $email)) {
    error('Invalid email.');
}
$prior = $DB->scalar("
    SELECT Email
    FROM invites
    WHERE InviterID = ?
        AND Email = ?
    ", $Viewer->id(), $email
);
if ($prior) {
    error('You already have a pending invite to that address!');
}

$inviteKey = randomString();
$DB->begin_transaction();
$DB->prepared_query("
    INSERT INTO invites
           (InviterID, InviteKey, Email, Reason, Expires)
    VALUES (?,         ?,         ?,     ?,      now() + INTERVAL 3 DAY)
    ", $Viewer->id(), $inviteKey, $email, trim($_POST['reason'] ?? '')
);
if (!$Viewer->permitted('site_send_unlimited_invites')) {
    $DB->prepared_query("
        UPDATE users_main SET
            Invites = GREATEST(Invites, 1) - 1
        WHERE ID = ?
        ", $Viewer->id()
    );
    $Viewer->flush();
}
if (isset($_POST['user-0']) && preg_match('/^s-(\d+)$/', $_POST['user-0'], $match)) {
    (new Gazelle\Manager\InviteSource)->createPendingInviteSource($match[1], $inviteKey);
}
$DB->commit();

(new Mail)->send($email, 'You have been invited to ' . SITE_NAME,
    $Twig->render('email/invite-member.twig', [
        'email'    => $email,
        'key'      => $inviteKey,
        'username' => $Viewer->username(),
    ])
);

header('Location: user.php?action=invite');
