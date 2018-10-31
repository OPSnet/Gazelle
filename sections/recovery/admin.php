<?
if (!check_perms('admin_recovery')) {
	error(403);
}
View::show_header('Recovery administration');

if (isset($_GET['task'])) {
    $id = (isset($_GET['id']) && (int)$_GET['id'] > 0)
        ? (int)$_GET['id']
        : 0;
    if ($id > 0) {
        switch ($_GET['task']) {
            case 'accept';
                $ok = \Gazelle\Recovery::accept($id, G::$LoggedUser['ID'], G::$LoggedUser['Username'], G::$DB);
                $message = $ok ? '<font color="#008000">Invite sent</font>' : '<font color="#800000">Invite not sent, check log</font>';
                break;
            case 'deny';
                \Gazelle\Recovery::deny($id, G::$LoggedUser['ID'], G::$LoggedUser['Username'], G::$DB);
                $message = sprintf('<font color="orange">Request %d was denied</font>', $id);
                break;
            case 'unclaim';
                \Gazelle\Recovery::unclaim($id, G::$LoggedUser['Username'], G::$DB);
                $message = sprintf('<font color="orange">Request %d was unclaimed</font>', $id);
                break;
            default:
                error(403);
                break;
        }
    }
}


$Page = (isset($_GET['page']) && (int)$_GET['page'] > 0)
    ? (int)$_GET['page'] : 1;
$Limit  = 100;
$Offset = $Limit * ($Page-1);

$State = isset($_GET['state']) ? $_GET['state'] : 'pending';
$Total = \Gazelle\Recovery::get_total($State, G::$LoggedUser['ID'], G::$DB);
$Info  = \Gazelle\Recovery::get_list($Limit, $Offset, $State, G::$LoggedUser['ID'], G::$DB);

$Pages = Format::get_pages($Page, $Total, $Limit);
?>

<div class="thin">

<div class="linkbox">
	<a href="/recovery.php?action=admin&amp;state=pending" class="brackets">Pending</a>
	<a href="/recovery.php?action=admin&amp;state=validated" class="brackets">Validated</a>
	<a href="/recovery.php?action=admin&amp;state=accepted" class="brackets">Accepted</a>
	<a href="/recovery.php?action=admin&amp;state=denied" class="brackets">Denied</a>
	<a href="/recovery.php?action=admin&amp;state=claimed" class="brackets">Your claimed</a>
</div>

<h3><?= $Total ?> <?= $State ?> recovery requests</h3>

<? if (isset($message)) { ?>
<h5><?= $message ?></h5>
<? } ?>

<div class="linkbox">
	<?=$Pages?>
</div>

<div class="box">
	<div class="head">Registrations</div>
	<div class="pad">
		<table>
			<tr>
				<th>ID</th>
				<th>Username</th>
				<th>Token</th>
				<th>Email</th>
				<th>Announce</th>
				<th>Created</th>
				<th>Updated</th>
                <th>Action</th>
			</tr>
<? foreach ($Info as $i) { ?>
			<tr>
				<td><?= $i['recovery_id'] ?></td>
				<td><?= $i['username'] ?></td>
				<td><tt><?= $i['token'] ?></tt></td>
				<td><?= $i['email'] ?></td>
				<td><?= $i['announce'] ?></td>
				<td><?= time_diff($i['created_dt']) ?></td>
				<td><?= time_diff($i['updated_dt']) ?></td>
                <td>
                    <a class="brackets" href="/recovery.php?action=view&amp;id=<?= $i['recovery_id'] ?>">View</a>
<?  if ($Info['state'] == 'PENDING') { ?>
                    <a class="brackets" href="/recovery.php?action=view&amp;id=<?= $i['recovery_id'] ?>&amp;claim=<?= G::$LoggedUser['ID'] ?>">Claim</a>
<?  } ?>
                </td>
			</tr>
<? } ?>
		</table>
	</div>
</div>

<div class="linkbox">
	<?=$Pages?>
</div>

</div>
<?
View::show_footer();

