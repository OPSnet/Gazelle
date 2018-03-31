<? View::show_header('Two-factor Authentication'); ?>
<span id="no-cookies" class="hidden warning">You appear to have cookies disabled.<br/><br/></span>
<noscript><span class="warning"><?= SITE_NAME ?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br/><br/>
</noscript>
<?
if (strtotime($BannedUntil) < time()) {
	?>
	<form class="auth_form" name="login" id="loginform" method="post" action="login.php?act=2fa">
		<?

		if (!empty($BannedUntil) && $BannedUntil != '0000-00-00 00:00:00') {
			$DB->query("
			UPDATE login_attempts
			SET BannedUntil = '0000-00-00 00:00:00', Attempts = '0'
			WHERE ID = '" . db_string($AttemptID) . "'");
			$Attempts = 0;
		} ?>
        <div id="login_wrapper">
            <div id="login_box">
                <div id="login_box_desc">
                    <? if (isset($Err)) { printf("%s ", $Err); } ?>
                    <? if ($Attempts > 0) { ?>You have <strong><?=(6 - $Attempts)?></strong> attempts remaining.<? } ?>
                </div>
                <div id="login_box_user"><input class="login_field" size="30" id="2fa" name="2fa" type="text" placeholder="Two-factor Auth Key" about="required="required"
                    maxlength="6" pattern="[0-9]{6}" autofocus="autofocus""/></div>
                <a id="login_box_rcvr" href="login.php?act=2fa_recovery" class="tooltip" title="Use 2FA Recovery Code">Use a recovery key?</a>
                <div id="login_box_sbmt"><input name="submit" value="come on in" type="submit"/></div>
            </div>
        <br>
        <p>Need help? join our irc channel: <u><a href="irc://irc.apollo.rip/#help">#support @ irc.sceneaccess.eu</a></u></p>
        <p class="small">Note: you will need an IRC client, such as <u><a href="https://www.mirc.com/get.html">mIRC</a></u>, to join our irc server.</p>
        </div>
	</form>
	<?
} else {
	?>
	<span class="warning">You are banned from logging in for another <?= time_diff($BannedUntil) ?>.</span>
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
</script>
<? View::show_footer(); ?>
