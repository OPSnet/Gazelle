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
    <a href="wiki.php?action=article&amp;name=bonuspoints" class="brackets">About Bonus Points</a>
    <a href="bonus.php?action=bprates" class="brackets">Bonus Point Rates</a>
    <a href="bonus.php?action=history" class="brackets">History</a>
</div>

<?
if (isset($_GET['action']) && $_GET['action'] == 'donate') {
    authorize();
    $value = $_POST['donate'];
    if (G::$LoggedUser['ID'] != $_POST['userid']) {
?>
<div class="alertbar blend">User error, no bonus points donated.</div>
<?
    }
    elseif (G::$LoggedUser['BonusPoints'] < $value) {
?>
<div class="alertbar blend">Warning! You cannot donate <?= number_format($value) ?> if you only have <?= number_format(G::$LoggedUser['BonusPoints']) ?> points.</div>
<?
    }
    elseif ($Bonus->donate($_POST['poolid'], $value, G::$LoggedUser['ID'], G::$LoggedUser['EffectiveClass'])) {
?>
<div class="alertbar blend">Success! Your donation to the Bonus Point pool has been recorded.</div>
<?
    }
    else {
?>
<div class="alertbar blend">No bonus points donated, insufficient funds.</div>
<?
    }
}

$pool = $Bonus->getOpenPool();
if (count($pool) > 0) {
?>
<div class="thin">
    <div class="box pad">
        <h3>The <b><?= $pool['Name'] ?></b> pool is open for business!</h3>
        <div>Donate points for greater good! The points you give here will be distributed out to everyone
        who participates in the contest. You can give as many times as you want until the end.</div>
        <br />
        <h3>The grand total currently stands at <?= number_format($pool['Total']) ?> points</h3>
        <form class="pool_form" name="pool" id="poolform" action="bonus.php?action=donate" method="post">
            <table>
                <thead>
                    <tr><th>Current BP</th><th>Donated BP</th></tr>
                <thead>
                <tbody>
                    <tr>
                        <td><?= number_format(G::$LoggedUser['BonusPoints']) ?></td>
                        <td><input type="text" width="10" name="donate" />
                            <input type="hidden" name="poolid" value="<?= $pool['Id'] ?>"/>
                            <input type="hidden" name="userid" value="<?= G::$LoggedUser['ID'] ?>"/>
                            <input type="hidden" name="auth" value="<?= G::$LoggedUser['AuthKey'] ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input type="submit" value="Donate" /></td>
                    </tr>
                </tbody>
            </table>
            <div><small>The fine print: obviously, you cannot donate more BP than you currently have. There are no refunds. Some handling fees apply.</small></div>
        </form>
    </div>
</div>
<?
} /* pool */
?>

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
					<a id="bonusconfirm" href="bonus.php?action=purchase&amp;label={$Label}&amp;auth={$LoggedUser['AuthKey']}" onclick="{$OnClick}(event, '{$Item['Title']}', $NextFunction, this);">Purchase</a>
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
    <br />
</div>
<?php

View::show_footer();
