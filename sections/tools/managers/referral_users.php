<?php
if (!check_perms('admin_view_referrals')) {
	error(403);
}

$ReferralManager = new Gazelle\Manager\Referral($DB, $Cache);

if (isset($_POST['id'])) {
	authorize();
	if (!check_perms('admin_manage_referrals')) {
		error(403);
	}

	$ReferralManager->deleteUserReferral($_POST['id']);
}

define('USERS_PER_PAGE', 50);
list($Page, $Limit) = Format::page_limit(USERS_PER_PAGE);

$StartDate = $_GET['start_date'];
$EndDate = $_GET['end_date'];

if (!empty($StartDate)) {
	list($Y, $M, $D) = explode('-', $StartDate);
	if (!checkdate($M, $D, $Y)) {
		$StartDate = NULL;
	}
} else {
	$StartDate = NULL;
}

if (!empty($EndDate)) {
	list($Y, $M, $D) = explode('-', $EndDate);
	if (!checkdate($M, $D, $Y)) {
		$EndDate = NULL;
	}
} else {
	$EndDate = NULL;
}

$View = isset($_GET['view']) ? $_GET['view'] : 'all';

$ReferredUsers = $ReferralManager->getReferredUsers($StartDate, $EndDate, $Limit, $View);

View::show_header('Referred Users');

?>

<div class="header">
	<h2>Referred users</h2>
</div>
<div class="linkbox">
	<a class="brackets" href="tools.php?action=referral_users">All</a>
	<a class="brackets" href="tools.php?action=referral_users&amp;view=pending">Pending</a>
	<a class="brackets" href="tools.php?action=referral_users&amp;view=processed">Processed</a>
</div>
<div class="thin box">
	<form class="manage_form" name="users" action="" method="get">
		<input type="hidden" name="action" value="referral_users" />
		<input type="hidden" name="view" value="<?=$View?>" />
		<div class="pad">
			<table class="layout">
				<tr>
					<td class="label"><label for="start_date">Start Date</label></td>
					<td><input type="text" name="start_date" size="10" value="<?=display_str($_GET['start_date'])?>" placeholder="YYYY-MM-DD" /></td>
				</tr>
				<tr>
					<td class="label"><label for="end_date">End Date</label></td>
					<td><input type="text" name="end_date" size="10" value="<?=display_str($_GET['end_date'])?>" placeholder="YYYY-MM-DD" /></td>
				</tr>
			</table>
			<div class="center">
				<input type="submit" name="submit" value="Submit" class="submit" />
			</div>
		</div>
	</form>
</div>

<?php if ($ReferredUsers["Results"] > 0) { ?>
<div class="linkbox">
	<?=Format::get_pages($Page, $ReferredUsers["Results"], USERS_PER_PAGE, 11)?>
</div>
<table width="100%">
	<tr class="colhead">
		<td>User</td>
		<td>Site</td>
		<td>Username</td>
		<td>Referred</td>
		<td>Joined</td>
		<td>Active</td>
<?php if (check_perms('users_view_invites')) { ?>
		<td>Invite</td>
<?php } if (check_perms('admin_manage_referrals')) { ?>
		<td></td>
<?php } ?>
	</tr>
<?php
$Row = 'b';
	foreach ($ReferredUsers["Users"] as $a) {
		list($ID, $UserID, $Site, $Username, $Referred, $Joined, $IP, $Active, $Invite) = array_values($a);
		$Row = $Row === 'a' ? 'b' : 'a';
?>
	<tr class="row<?=$Row?>">
		<form class="manage_form" name="accounts" action="" method="post">
			<input type="hidden" name="id" value="<?=$ID?>" />
			<input type="hidden" name="action" value="referral_users" />
			<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
			<td>
				<?=$UserID ? Users::format_username($UserID, true, true, true, true) : ""?>
			</td>
			<td>
				<?=$Site?>
			</td>
			<td>
				<?=$Username?>
			</td>
			<td>
				<?=Gazelle\Util\Time::timeDiff($Referred)?>
			</td>
			<td>
				<?=Gazelle\Util\Time::timeDiff($Joined)?>
			</td>
			<td>
				<input type="checkbox" name="active" disabled="disabled"<?=($Active == '1') ? ' checked="checked"' : ''?> />
			</td>
<?php	if (check_perms('users_view_invites')) { ?>
			<td>
<?php		if (!$Active) { ?>
				<a href="https://<?=SITE_URL?>/register.php?invite=<?=$Invite?>"><?=$Invite?></a>
<?php		} ?>
			</td>
<?php } if (check_perms('admin_manage_referrals')) { ?>
			<td>
				<input type="submit" name="submit" value="Unlink" onclick="return confirm('Are you sure you want to unlink this account? This is an irreversible action!')" />
			</td>
<?php	} ?>
		</form>
	</tr>
<?php
	}
?>
</table>
<div class="linkbox">
	<?=Format::get_pages($Page, $ReferredUsers["Results"], USERS_PER_PAGE, 11)?>
</div>
<?php
} else {
?>
<div class="center">
	<h2>No users found</h2>
</div>
<?php
}
View::show_footer();
?>

