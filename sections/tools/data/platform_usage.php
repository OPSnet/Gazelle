<?php

if (!check_perms('site_view_flow')) {
	error(403);
}

View::show_header('OS and Browser Usage');

?>
	<div class="header">
		<h2>OS Usage</h2>
	</div>
	<table width="100%">
		<tr class="colhead">
			<td>OS</td>
			<td>Count</td>
		</tr>

		<?php
		G::$DB->prepared_query("SELECT OperatingSystem, COUNT(*) FROM users_sessions GROUP BY OperatingSystem ORDER BY COUNT(*) DESC");
		while (list($OperatingSystem, $Count) = G::$DB->fetch_record(0, 'OperatingSystem')) {
			?>
			<tr>
				<td><?=$OperatingSystem?></td>
				<td><?=$Count?></td>
			</tr>
			<?php
		}
		?>
	</table>
<?php

?>
<div class="header">
	<h2>Browser Usage</h2>
</div>
<table width="100%">
	<tr class="colhead">
		<td>Browser</td>
		<td>Count</td>
	</tr>

<?php
G::$DB->prepared_query("SELECT Browser, COUNT(*) FROM users_sessions GROUP BY Browser ORDER BY COUNT(*) DESC");
while (list($Browser, $Count) = G::$DB->fetch_record(0, 'Browser')) {
	?>
	<tr>
		<td><?=$Browser?></td>
		<td><?=$Count?></td>
	</tr>
	<?php
}
?>
</table>
<?php

View::show_footer();
