<?php

View::show_header('Bonus Points Shop');

?>
<div class="header">
	<h2>Bonus Points Shop</h2>
</div>
<div class="linkbox">
	<a href="wiki.php?action=article&id=130" class="brackets">About Bonus Points</a>
	<a href="bonus.php?action=bprates" class="brackets">Bonus Point Rates</a>
</div>
<div class="thin">
	<table>
		<thead>
			<tr class="colhead">
				<td width="30px">Option</td>
				<td>Description</td>
				<td width="45px">Points</td>
				<td width="70px"></td>
			</tr>
		</thead>
		<tbody>
<?php

foreach ($Options as $Key => $Option) {
	$Key += 1;
	$RowClass = ($Key % 2 === 0) ? 'rowb' : 'rowa';
	$Price = number_format($Option['Price']);
	print <<<HTML
			<tr class="$RowClass">
				<td>{$Key}</td>
				<td>{$Option['Title']}</td>
				<td>{$Price}</td>
				<td>
HTML;

	if (G::$LoggedUser['BonusPoints'] >= $Option['Price']) {
		print <<<HTML
					<form action="bonus.php?action={$Option['Action']}">
HTML;

		foreach ($Option['Hidden'] as $Name => $Value) {
			print "\t\t\t\t\t\t<input type='hidden' name='{$Name}' value='{$Value}' />";
		}

		print <<<HTML
						<input type="submit" value="Exchange" />
					</form>
HTML;
	}
	else {
		print <<<HTML
					<input type="submit" disabled="disabled" value="Exchange" />
HTML;

	}
}
?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<?php

View::show_footer();
?>