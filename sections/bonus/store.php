<?php

View::show_header('Bonus Points Shop', 'bonus');

if (isset($_GET['complete'])) {
    $label = $_GET['complete'];
    $item = $Bonus->getItem($label);
	print <<<HTML
<div class="alertbar blend">
	{$item['Title']} purchased!
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
	<a href="bonus.php?action=history" class="brackets">History</a>
</div>

<div class="thin">
	<table>
		<thead>
			<tr class="colhead">
				<td width="30px">Option</td>
				<td>Description</td>
				<td width="45px">Points</td>
				<td width="70px">Checkout</td>
			</tr>
		</thead>
		<tbody>
<?php

$Cnt = 0;
$Items = $Bonus->getList();

foreach ($Items as $Label => $Item) {
	if ($Item['MinClass'] >  G::$LoggedUser['EffectiveClass']) {
		continue;
	}
	$Cnt++;
	$RowClass = ($Cnt % 2 === 0) ? 'rowb' : 'rowa';
	$Price = $Bonus->getEffectivePrice($Label, G::$LoggedUser['EffectiveClass']);
	$FormattedPrice = number_format($Price);
	print <<<HTML
			<tr class="$RowClass">
				<td>{$Cnt}</td>
				<td>{$Item['Title']}</td>
				<td>{$FormattedPrice}</td>
				<td>
HTML;

	if (G::$LoggedUser['BonusPoints'] >= $Price) {
        $NextFunction = preg_match('/^other-\d$/',          $Label) ? 'ConfirmOther' : 'null';
        $OnClick      = preg_match('/^title-bbcode-[yn]$/', $Label) ? "NoOp" : "ConfirmPurchase";
		print <<<HTML
					<a id="bonusconfirm" href="bonus.php?action=purchase&label={$Label}&auth={$LoggedUser['AuthKey']}" onclick="{$OnClick}(event, '{$Item['Title']}', $NextFunction, this);">Purchase</a>
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
}
?>
		</tbody>
	</table>
</div>
<?php

View::show_footer();
