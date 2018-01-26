<?php
if (!check_perms('admin_manage_stylesheets')) {
	error(403);
}
View::show_header('Manage Stylesheets');
?>
<div class="thin">
	<div class="header">
		<div class="linkbox">
			<a href="tools.php" class="brackets">Back to tools</a>
		</div>
	</div>
	<?php
	$DB->prepared_query("
	SELECT
		s.ID,
		s.Name,
		s.Description,
		s.`Default`,
		IFNULL(ui.`Count`, 0),
		IFNULL(ud.`Count`, 0)
	FROM stylesheets AS s
	LEFT JOIN (
		SELECT StyleID, COUNT(*) AS Count FROM users_info AS ui JOIN users_main AS um ON ui.UserID = um.ID WHERE um.Enabled='1' GROUP BY StyleID
	) AS ui ON s.ID=ui.StyleID
	LEFT JOIN (
		SELECT StyleID, COUNT(*) AS Count FROM users_info AS ui JOIN users_main AS um ON ui.UserID = um.ID GROUP BY StyleID
	) AS ud ON s.ID = ud.StyleID
	ORDER BY s.ID");
	if ($DB->has_results()) {
		?>
		<table width="100%">
			<tr class="colhead">
				<td>Name</td>
				<td>Description</td>
				<td>Default</td>
				<td>Count</td>
			</tr>
			<?php
			while (list($ID, $Name, $Description, $Default, $EnabledCount, $TotalCount) = $DB->next_record(MYSQLI_NUM, array(1, 2))) { ?>
				<tr>
					<td><?=$Name?></td>
					<td><?=$Description?></td>
					<td><?=($Default == '1') ? 'Default' : ''?></td>
					<td><?=number_format($EnabledCount)?> (<?=number_format($TotalCount)?>)</td>
				</tr>
			<?php	} ?>
		</table>
		<?php
	} else { ?>
		<h2 align="center">There are no stylesheets.</h2>
		<?php
	} ?>
</div>
<?php
View::show_footer();
