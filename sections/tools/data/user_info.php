<?php

if (!check_perms('users_view_ips')) {
    error(403);
}

$UserID = (int)$_GET['userid'];
if (!$UserID) {
    error(404);
}
$user = new Gazelle\User($UserID);

View::show_header('User information');
?>
<div class="box pad center">
<h2>Information on <a href="/user.php?id=<?= $UserID ?>"><?= $user->info()['Username'] ?></a></h2>
<table>
<tr><th>Now</th><td colspan="2"><?= Date('Y-m-d H:i:s') ?></td></tr>
<tr><th>Last seen</th><td colspan="2"><?= $user->lastAccess() ?></td></tr>
<tr><th>Joined</th><td colspan="2"><?= $user->joinDate() ?></td></tr>
<?php
echo G::$Twig->render('admin/user-info-email.twig', [
    'info'   => $user->emailHistory(),
]);

echo G::$Twig->render('admin/user-info-ipv4.twig', [
    'title'  => 'Site IPv4 Information',
    'info'   => $user->siteIPv4Summary(),
]);

echo G::$Twig->render('admin/user-info-ipv4.twig', [
    'title'  => 'Tracker IPv4 Information',
    'info'   => $user->trackerIPv4Summary(),
]);
?>
</table>

<?php
View::show_footer();
