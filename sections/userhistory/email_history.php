<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

$user = (new Gazelle\Manager\User())->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

echo $Twig->render('user/email-history.twig', [
    'asn'     => new Gazelle\Search\ASN(),
    'history' => new Gazelle\User\History($user),
    'user'    => $user,
]);
