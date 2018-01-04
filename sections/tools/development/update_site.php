<?php

$Method = '';
if (isset($_GET['method'])) {
	authorize();
	$Method = $_GET['method'];
}

$Classes = array(SYSOP);
if (defined('LEAD_DEV')) {
	$Classes[] = LEAD_DEV;
}

if (!check_perms('site_debug') || !in_array(G::$LoggedUser['PermissionID'], $Classes)) {
	error(403);
}

View::show_header('Update Site');

// Note: The shell execs are operating from the root of the gazelle repo
$GitOutput = '';
if ($Method == 'git_pull') {
	$GitOutput = shell_exec('git pull 2>&1');
}
$GitBranch = shell_exec('git rev-parse --abbrev-ref HEAD');
$GitHash = shell_exec('git rev-parse HEAD');
$RemoteHash = shell_exec("git rev-parse origin/{$GitBranch}");

// If composer detects xdebug is running, it'll disable it and then restart PHP which shell_exec really doesn't like
$ComposerVersion = substr(shell_exec('COMPOSER_ALLOW_XDEBUG=1 composer --version'), 16);

$Packages = [];
$ComposerOutput = '';
if ($Method == 'composer_install') {
	$ComposerOutput = nl2br(shell_exec('COMPOSER_ALLOW_XDEBUG=1 composer install 2>&1'));
}
elseif ($Method == 'composer_dump_autoload') {
	$ComposerOutput = nl2br(shell_exec('COMPOSER_ALLOW_XDEBUG=1 composer dump-autoload 2>&1'));
}

$Composer = json_decode(file_get_contents(__DIR__.'/../../../composer.json'), true);
foreach ($Composer['require'] as $Package => $Version) {
	$Packages[$Package] = ['Name' => $Package, 'Version' => $Version];
}
$ComposerLock = json_decode(file_get_contents(__DIR__.'/../../../composer.lock'), true);
foreach ($ComposerLock['packages'] as $Package) {
	if (isset($Packages[$Package['name']])) {
		$Packages[$Package['name']]['Locked'] = $Package['version'];
	}
}
$ComposerPackages = json_decode(shell_exec('COMPOSER_ALLOW_XDEBUG=1 composer info --format=json'), true);
foreach ($ComposerPackages['installed'] as $Package) {
	if (isset($Packages[$Package['name']])) {
		$Packages[$Package['name']]['Installed'] = $Package['version'];
	}
}

$PhinxVersion = shell_exec('vendor/bin/phinx --version');
$PhinxOutput = '';
if ($Method == 'phinx_migrate') {
	$PhinxOutput = nl2br(shell_exec('vendor/bin/phinx migrate -e apollo'));
}
elseif ($Method == 'phinx_rollback') {
	$PhinxOutput = nl2br(shell_exec('vendor/bin/phinx rollback -e apollo'));
}
$PhinxMigrations = array_filter(json_decode(shell_exec('vendor/bin/phinx status -e apollo --format=json | tail -n 1'), true)['migrations'], function($value) { return count($value) > 0; });

?>
<div class="thin">
	<div class="header">
		<div class="linkbox">
			<a href="tools.php?action=update_site" class="brackets">View basic page</a>
			<a href="tools.php" class="brackets">Back to tools</a>
		</div>
	</div>
	<h3>Git</h3>
	<div class="box pad">
		<span style="width: 150px; display: inline-block;">Branch:</span> <?=$GitBranch?><br />
		<span style="width: 150px; display: inline-block;">Local Hash:</span> <?=$GitHash?><br />
		<span style="width: 150px; display: inline-block;">Remote Hash:</span> <?=$RemoteHash?><br />
		<?php
		if ($GitOutput !== '') {
			print "Pull Results:<br />{$GitOutput}<br />";
		}
		?>
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=git_pull&auth=<?=G::$LoggedUser['AuthKey']?>';" value="git pull" />
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=git_reset&auth=<?=G::$LoggedUser['AuthKey']?>';" value="git reset --hard HEAD~1" />
	</div>
	<h3>Composer</h3>
	<div class="box pad">
		Composer Version: <?=$ComposerVersion?><br />
		<table>
			<tr class="colhead">
				<td>Package</td>
				<td>Version</td>
				<td>Installed</td>
				<td>Locked</td>
			</tr>
		<?php
		foreach ($Packages as $Package) {
			?>
			<tr>
				<td><?=$Package['Name']?></td>
				<td><?=$Package['Version']?></td>
				<td><?=$Package['Installed']?></td>
				<td><?=$Package['Locked']?></td>
			</tr>
			<?php
		}
		?>
		</table>
		<?php
		if ($ComposerOutput !== '') {
			print "Composer Command Result:<br />{$ComposerOutput}<br />";
		}
		?>
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=composer_install&auth=<?=G::$LoggedUser['AuthKey']?>';" value="composer install" />
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=composer_dump_autoload&auth=<?=G::$LoggedUser['AuthKey']?>';" value="composer dump-autoload" />
	</div>
	<h3>Phinx</h3>
	<div class="box pad">
		<?=$PhinxVersion?><br />
		<table>
			<tr class='colhead'>
				<td>Status</td>
				<td>Migration ID</td>
				<td>Migration Name</td>
			</tr>
			<?php
			foreach($PhinxMigrations as $Migration) {
				?>
			<tr>
				<td><?=$Migration['migration_status']?></td>
				<td><?=$Migration['migration_id']?></td>
				<td><?=$Migration['migration_name']?></td>
			</tr>
				<?php
			}
			?>
		</table>
		<?php
		if ($PhinxOutput !== '') {
			print "Phinx Command Result:<br />{$PhinxOutput}<br />";
		}
		?>
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=phinx_migrate&auth=<?=G::$LoggedUser['AuthKey']?>';" value="phinx migrate" />
		<input type="button" onclick="window.location.href='tools.php?action=update_site&method=phinx_rollback&auth=<?=G::$LoggedUser['AuthKey']?>';" value="phinx rollback" />
	</div>
</div>
<?php

View::show_footer();