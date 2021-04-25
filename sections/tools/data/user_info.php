<?php

if (!check_perms('users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)$_GET['userid']);
if (is_null($user)) {
    error(404);
}

View::show_header($user->username() . ' &rsaquo; Email and IP summary');
?>
<div class="box pad center">
<h2><a href="/user.php?id=<?= $user->id() ?>"><?= $user->username() ?></a> &rsaquo; Email and IP summary</h2>
<table>
<tr><th>Now</th><td colspan="2"><?= Date('Y-m-d H:i:s') ?></td></tr>
<tr><th>Last seen</th><td colspan="2"><?= $user->lastAccess() ?></td></tr>
<tr><th>Joined</th><td colspan="2"><?= $user->joinDate() ?></td></tr>
<?php
echo $Twig->render('admin/user-info-email.twig', [
    'info'   => $user->emailHistory(),
]);

echo $Twig->render('admin/user-info-ipv4.twig', [
    'title'  => 'Site IPv4 Information',
    'info'   => $user->siteIPv4Summary(),
]);

echo $Twig->render('admin/user-info-ipv4.twig', [
    'title'  => 'Tracker IPv4 Information',
    'info'   => $user->trackerIPv4Summary(),
]);
?>
</table>

<?php
View::show_footer();
