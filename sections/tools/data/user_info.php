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
<h2>Information on <?= $user->info()['Username'] ?></h2>
<table>
<tr><th>Now</th><td colspan="2"><?= Date('Y-m-d H:i:s') ?></td></tr>
<tr><th>Last seen</th><td colspan="2"><?= $user->lastAccess() ?></td></tr>
<tr><th>Joined</th><td colspan="2"><?= $user->joinDate() ?></td></tr>

<tr><th colspan="3">Email Information</th></tr>
<tr><th>Address</th><th>Registered from</th><th>Registered at</th></tr>
<?php
$emailHist = $user->emailHistory();
foreach ($emailHist as $e) {
?>
<tr><td><?= $e[0] ?></td><td><?= $e[1] ?></td><td><?= $e[2] ?></td></tr>
<?php
}
unset($emailHist);
?>

<tr><th colspan="3">Site IPv4 Information</th></tr>
<tr><th>Address</th><th>First seen</th><th>Last seen</th></tr>
<?php
$ipSummary = $user->siteIPv4Summary();
foreach ($ipSummary as $s) {
?>
<tr><td><?= $s[0] ?></td><td><?= $s[1] ?></td><td><?= $s[2] ?></td></tr>
<?php
}
unset($ipSummary);
?>

<tr><th colspan="3">Tracker IPv4 Information</th></tr>
<tr><th>Address</th><th>First seen</th><th>Last seen</th></tr>
<?php
$ipSummary = $user->trackerIPv4Summary();
foreach ($ipSummary as $s) {
?>
<tr><td><?= $s[0] ?></td><td><?= $s[1] ?></td><td><?= $s[2] ?></td></tr>
<?php
}
unset($ipSummary);
?>
</table>

<?php
View::show_footer();
