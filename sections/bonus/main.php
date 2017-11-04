<?php

View::show_header('Bonus Points Shop', 'bonus');

if (isset($_GET['complete'])) {
	print <<<HTML
<div class="alertbar blend">
	Item Purchased!
</div>
HTML;
}

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

$Cnt = 1;
foreach ($Items as $Key => $Item) {
	$RowClass = ($Cnt % 2 === 0) ? 'rowb' : 'rowa';
	$Price = number_format($Item['Price']);
	print <<<HTML
			<tr class="$RowClass">
				<td>{$Cnt}</td>
				<td>{$Item['Title']}</td>
				<td>{$Price}</td>
				<td>
HTML;

	if (G::$LoggedUser['BonusPoints'] >= $Item['Price']) {
		$Url = array();
		foreach ($Item['Options'] as $Key => $Value) {
			$Url[] = "{$Key}={$Value}";
		}
		$Url = implode("&", $Url);
		$Onclick = (isset($Item['Onclick'])) ? "onclick='{$Item['Onclick']}(this)'" : '';
		print <<<HTML
					<a href="bonus.php?action={$Item['Action']}&{$Url}" {$Onclick}>Purchase</a>
HTML;
	}
	else {
		print <<<HTML
					<span style="font-style: italic">Too Expensive</span>
HTML;

	}
	print <<<HTML
				</td>
	</tr>
HTML;

	$Cnt++;
}
?>
		</tbody>
	</table>
</div>
<?php

View::show_footer();
?>