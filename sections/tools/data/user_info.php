<?php

// @phpstan-var \Gazelle\User $Viewer
// @phpstan-var \Twig\Environment $Twig

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$userMan = new Manager\User();
$user = $userMan->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    Error404::error();
}

$column    = $_GET['col'] ?? 'first';
$direction = $_GET['dir'] ?? 'up';

echo $Twig->render('admin/user-info.twig', [
    'ancestry'      => $userMan->ancestry($user),
    'asn'           => new Search\ASN(),
    'column'        => $column,
    'direction'     => $direction,
    'invite_source' => new Manager\InviteSource(),
    'hist'          => new User\History($user, $column, $direction),
    'user'          => $user,
]);
