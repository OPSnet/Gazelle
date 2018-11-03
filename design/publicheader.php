<?php
global $LoggedUser, $SSL;
define('FOOTER_FILE',SERVER_ROOT.'/design/publicfooter.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title><?=display_str($PageTitle)?></title>
	<meta http-equiv="X-UA-Compatible" content="chrome=1; IE=edge" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="referrer" content="none, no-referrer, same-origin" />
	<link rel="shortcut icon" href="favicon.ico" />
	<link rel="apple-touch-icon" href="/apple-touch-icon.png" />
	<meta name="viewport" content="width=device-width; initial-scale=1.0;" />
	<?
	$styles = [];
	list($month, $day) = explode(' ', date('n d'));
	if (($month == 12 && $day >= 12) || ($month == 1 && $day < 4)) {
		$styles = array_merge($styles, ['red', 'green', 'white']);
	}
	$style = (count($styles) > 0) ? $styles[array_rand($styles)] : '';
	?>
	<link href="<?=STATIC_SERVER ?>styles/public/style<?=$style?>.css?v=<?=filemtime(SERVER_ROOT."/static/styles/public/style{$style}.css")?>" rel="stylesheet" type="text/css" />
	<script src="<?=STATIC_SERVER?>functions/jquery.js" type="text/javascript"></script>
	<script src="<?=STATIC_SERVER?>functions/script_start.js?v=<?=filemtime(SERVER_ROOT.'/static/functions/script_start.js')?>" type="text/javascript"></script>
	<script src="<?=STATIC_SERVER?>functions/ajax.class.js?v=<?=filemtime(SERVER_ROOT.'/static/functions/ajax.class.js')?>" type="text/javascript"></script>
	<script src="<?=STATIC_SERVER?>functions/cookie.class.js?v=<?=filemtime(SERVER_ROOT.'/static/functions/cookie.class.js')?>" type="text/javascript"></script>
	<script src="<?=STATIC_SERVER?>functions/storage.class.js?v=<?=filemtime(SERVER_ROOT.'/static/functions/storage.class.js')?>" type="text/javascript"></script>
	<script src="<?=STATIC_SERVER?>functions/global.js?v=<?=filemtime(SERVER_ROOT.'/static/functions/global.js')?>" type="text/javascript"></script>
</head>
<body>
<div id="head">
</div>
<table class="layout" id="maincontent">
	<tr>
		<td align="center" valign="middle">
			<div id="logo" style="width:250px;">
<?php if (OPEN_REGISTRATION || OPEN_EXTERNAL_REFERRALS || RECOVERY) { ?>
				<ul>
					<li><a href="index.php">Home</a></li>
					<li><a href="login.php">Log in</a></li>
<?php if (OPEN_REGISTRATION) { ?>
					<li><a href="register.php">Register</a></li>
<?php } if (OPEN_EXTERNAL_REFERRALS) { ?>
					<li><a href="referral.php">Referrals</a></li>
<?php } if (RECOVERY) { ?>
					<li><a title="Obtain a new account by proving your membership on the previous site" href="recovery.php">Recovery</a></li>
<?php } ?>
				</ul>
<? } ?>
			</div>
			<p>
				Welcome Home!
				<br /><br />
				If you had an account on Apollo at the time of the backup (June 2017), please use the recovery page to restore your account.
				<br /><br />
				If you are unsure if you are in the backup or not, use the recovery page.
				<br /><br />
				If you had an account on Apollo but you signed up after the backup date, you can use either the Referral page (if you are on PTP, BTN, MTV, GGN, EMP or 32P) or the Recovery page. To save us work and to ensure immediate registration, please use the Referral page if you can.
				<br /><br />
				If you did not have an account on Apollo but you would like to join Orpheus, and you are on PTP, BTN, MTV, GGN, EMP or 32P, feel free to use the referral page and join!
				<br /><br />
				See you on the other side.
			</p>
			<br />
