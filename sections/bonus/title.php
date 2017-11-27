<?php

$ID = G::$LoggedUser['ID'];
$BBCode = (isset($_REQUEST['BBCode']) && $_REQUEST['BBCode'] === 'true') ? 'true' : 'false';
$Option = (isset($_REQUEST['BBCode']) && $_REQUEST['BBCode'] === 'true') ? 'title_bbcode' : 'title_nobbcode';
$Item = Bonus::$Items[$Option];
$Price = Bonus::get_price($Item);

if (isset($_REQUEST['preview'])) {
	$Title = ($BBCode === 'true') ? Text::full_format($_POST['title']) : Text::strip_bbcode($_POST['title']);
	print($Title);
	die();
}
if (isset($_REQUEST['Remove']) && $_REQUEST['Remove'] === 'true') {
	G::$DB->query("UPDATE users_main SET Title='' WHERE ID={$ID}");
	G::$Cache->delete_value("user_info_{$ID}");
	G::$Cache->delete_value("user_stats_{$ID}");
	header('Location: bonus.php?complete');
}
elseif (isset($_POST['confirm'])) {
	if (!isset($_POST['title'])) {
		error(403);
	}

	if ($Price > G::$LoggedUser['BonusPoints']) {
		error('You cannot afford this item.');
	}
	$Title = ($BBCode === 'true') ? Text::full_format($_POST['title']) : Text::strip_bbcode($_POST['title']);
	G::$DB->query("UPDATE users_main SET Title='".db_string($Title)."', BonusPoints=BonusPoints - {$Price} WHERE ID={$ID}");
	G::$Cache->delete_value("user_info_{$ID}");
	G::$Cache->delete_value("user_stats_{$ID}");
	header('Location: bonus.php?complete');
}
else {

	$Title = ($BBCode !== 'true') ? 'no BBCode allowed' : 'BBCode allowed';

	View::show_header('Bonus Points - Title', 'bonus');
	?>
	<div class="thin">
		<table>
			<thead>
			<tr>
				<td>Custom Title, <?=$Title?> - <?=number_format($Price)?> Points</td>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td>
					<form action="bonus.php?action=title&BBCode=<?=$BBCode?>" method="post">
						<input type="hidden" name="confirm" value="true" />
						<input type="text" style="width: 98%" id="title" name="title" placeholder="Custom Title"/> <br />
						<input type="submit" onclick="ConfirmPurchase('<?=$Item['Title']?>')" value="Submit" />&nbsp;<input type="button" onclick="PreviewTitle(<?=$BBCode?>);" value="Preview" /><br /><br />
						<div id="preview"></div>
					</form>
				</td>
			</tr>
			</tbody>
		</table>
	</div>
	<?php

	View::show_footer();
}

?>