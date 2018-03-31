<? View::show_header('Login'); ?>
	<span id="no-cookies" class="hidden warning">You appear to have cookies disabled.<br /><br /></span>
	<noscript><span class="warning"><?=SITE_NAME?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br /><br /></noscript>
<?
if (strtotime($BannedUntil) < time()) {
?>
	<form class="auth_form" name="login" id="loginform" method="post" action="login.php">
<?

	if (!empty($BannedUntil) && $BannedUntil != '0000-00-00 00:00:00') {
		$DB->query("
			UPDATE login_attempts
			SET BannedUntil = '0000-00-00 00:00:00', Attempts = '0'
			WHERE ID = '".db_string($AttemptID)."'");
		$Attempts = 0;
	}
?>
	<div id="login_wrapper">
		<div id="login_box">
			<div id="login_box_desc">
				<? if (isset($Err)) { printf("%s ", $Err); } ?>
				<? if (isset($_GET['invalid2fa'])) { echo '2FA Failed.'; } ?>
				<? if ($Attempts > 0) { ?>You have <strong><?=(6 - $Attempts)?></strong> attempts remaining.<? } ?>
			</div>
			<div id="login_box_user"><input class="login_field" placeholder="username" size="30" name="username" type="text" placeholder="username"/></div>
			<div id="login_box_pass"><input class="login_field" placeholder="password" size="30" name="password" type="password" placeholder="password"/></div>
			<div id="login_box_rcvr">
				<label for="keeplogged">Remember me</label>
				<input type="checkbox" id="keeplogged" name="keeplogged" value="1"<?=(isset($_REQUEST['keeplogged']) && $_REQUEST['keeplogged']) ? ' checked="checked"' : ''?> />
			</div>
			<div id="login_box_sbmt"><input name="submit" value="come on in" type="submit"/></div>
        </div>
        <br>
        <p>Need help? join our irc channel: <u><a href="irc://irc.apollo.rip/#help">#support @ irc.sceneaccess.eu</a></u></p>
        <p class="small">Note: you will need an IRC client, such as <u><a href="https://www.mirc.com/get.html">mIRC</a></u>, to join our irc server.</p>
    </div>
<? } else { ?>
	<span class="warning">You are banned from logging in for another <?=time_diff($BannedUntil)?>.</span>
<?
}

if ($Attempts > 0) {
?>
	<br /><br />
	Lost your password? <a href="login.php?act=recover" class="tooltip" title="Recover your password">Recover it here!</a>
<?
}
?>
<script type="text/javascript">
cookie.set('cookie_test', 1, 1);
if (cookie.get('cookie_test') != null) {
	cookie.del('cookie_test');
} else {
	$('#no-cookies').gshow();
}
window.onload = function() {document.getElementById("username").focus();};
</script>
<? View::show_footer();
