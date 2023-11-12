<?php

authorize();

if (!isset($_POST['agreement'])) {
    error("You must agree to the conditions for sending invitations.");
}

// Can the site allow an invite to be spent?
if (!(new Gazelle\Stats\Users)->newUsersAllowed($Viewer) || !$Viewer->canInvite()) {
    error(403);
}
$email = trim($_POST['email'] ?? '');
if (!preg_match(EMAIL_REGEXP, $email)) {
    error('Invalid email.');
}

$manager = new Gazelle\Manager\Invite;
if ($manager->emailExists($Viewer, $email)) {
    error('You already have a pending invite to that address!');
}
$invite = $manager->create(
    $Viewer,
    $email,
    trim($_POST['notes'] ?? ''),
    trim($_POST['reason'] ?? ''),
    $_POST['user-0'] ?? ''
);

if (!$invite) {
    error(403);
}

(new \Gazelle\Util\Mail)->send($email, 'You have been invited to ' . SITE_NAME,
    $Twig->render('email/invite-member.twig', [
        'email'    => $email,
        'key'      => $invite->key(),
        'username' => $Viewer->username(),
    ])
);

header('Location: user.php?action=invite');
