<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}


$userMan = new Gazelle\Manager\User();
$user = $userMan->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}

$column    = $_GET['col'] ?? 'first';
$direction = $_GET['dir'] ?? 'up';

echo $Twig->render('admin/user-info.twig', [
    'ancestry'      => $userMan->ancestry($user),
    'asn'           => new Gazelle\Search\ASN(),
    'column'        => $column,
    'direction'     => $direction,
    'invite_source' => new Gazelle\Manager\InviteSource(),
    'hist'          => new Gazelle\User\History($user, $column, $direction),
    'now'           => date('Y-m-d H:i:s'),
    'user'          => $user,
]);
