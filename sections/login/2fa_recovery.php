<?php View::show_header('Two-factor Authentication'); ?>
<span id="no-cookies" class="hidden warning">You appear to have cookies disabled.<br/><br/></span>
<noscript><span class="warning"><?= SITE_NAME ?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br/><br/>
</noscript>
<?php if (strtotime($BannedUntil) >= time()) { ?>
    <span class="warning">You are banned from logging in for another <?= time_diff($BannedUntil) ?>.</span>
<?php } else { ?>
    <form class="auth_form" name="login" id="loginform" method="post" action="login.php?act=2fa_recovery">
<?php
        if ($BannedUntil) {
            $DB->prepared_query("
                UPDATE login_attempts
                SET BannedUntil = NULL, Attempts = 0
                WHERE ID = ?
                ", $AttemptID
            );
            $Attempts = 0;
        }
        if (isset($Err)) {
?>
            <span class="warning"><?= $Err ?><br/><br/></span>
<?php } ?>
<?php if ($Attempts > 0) { ?>
            You have <span class="info"><?= (6 - $Attempts) ?></span> attempts remaining.<br/><br/>
            <strong>WARNING:</strong> You will be banned for 6 hours after your login attempts run out!<br/><br/>
<?php } ?>
<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div style="width: 33%; margin: auto;">
        Note: A 2FA backup key can be used only once!
        <table class="layout">
            <tr>
                <td>2FA Backup Key&nbsp;</td>
                <td colspan="2">
                    <input type="text" name="2fa_recovery_key" id="2fa_recovery_key" class="inputtext" required="required"
                           autofocus="autofocus" placeholder="2FA Backup Key"/>
                </td>
            </tr>

            <tr>
                <td></td>
                <td><input type="submit" name="login" value="Log in via 2FA backup" class="submit"/></td>
            </tr>
        </table>
    </form>
    <br /><br />
<?php } ?>
<script type="text/javascript">
    cookie.set('cookie_test', 1, 1);
    if (cookie.get('cookie_test') != null) {
        cookie.del('cookie_test');
    } else {
        $('#no-cookies').gshow();
    }
</script>
<?php
View::show_footer();
