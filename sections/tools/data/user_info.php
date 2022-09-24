<?php

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}

$column    = $_GET['col'] ?? 'ip';
$direction = $_GET['dir'] ?? 'up';

echo $Twig->render('admin/user-info.twig', [
    'asn'       => new Gazelle\Search\ASN,
    'column'    => $column,
    'direction' => $direction,
    'hist'      => new Gazelle\User\History($user, $column, $direction),
    'now'       => Date('Y-m-d H:i:s'),
    'user'      => $user,
]);
