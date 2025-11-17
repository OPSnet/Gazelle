<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!isset($_POST['agreement'])) {
    Error400::error("You must agree to the conditions for sending invitations.");
}

// Can the site allow an invite to be spent?
if (!new Stats\Users()->newUsersAllowed($Viewer) || !$Viewer->canInvite()) {
    Error403::error();
}
$email = trim($_POST['email'] ?? '');
if (!preg_match(EMAIL_REGEXP, $email)) {
    Error400::error('Invalid email.');
}

$manager = new Manager\Invite();
if ($manager->emailExists($Viewer, $email)) {
    Error403::error('You already have a pending invite to that address!');
}

$notes  = '';
$reason = '';
$source = '';

if ($Viewer->isInterviewer() || $Viewer->permitted('users_invite_notes')) {
    $notes = trim($_POST['notes'] ?? '');
}

$inviteSourceMan = null;
if ($Viewer->isRecruiter() || $Viewer->isStaff()) {
    $inviteSourceMan = new Manager\InviteSource();
    $reason = trim($_POST['profile_info'] ?? '');
}

if ($inviteSourceMan && isset($_POST['user-0'])) {
    $submittedSource = (int)$_POST['user-0'];
    foreach ($inviteSourceMan->inviterConfigurationActive($Viewer) as $sourceConfig) {
        if ($sourceConfig['invite_source_id'] === $submittedSource) {
            $source = $_POST['user-0'];
            break;
        }
    }
}

$invite = $manager->create(
    $Viewer,
    $email,
    $notes,
    $reason,
    $source
);

if (!$invite) {
    Error403::error();
}

new \Gazelle\Util\Mail()->send($email, 'You have been invited to ' . SITE_NAME,
    $Twig->render('email/invite-member.twig', [
        'email'    => $email,
        'key'      => $invite->key(),
        'username' => $Viewer->username(),
    ])
);

header('Location: user.php?action=invite');
