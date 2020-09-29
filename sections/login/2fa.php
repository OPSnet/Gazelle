<?php View::show_header('Two-factor Authentication'); ?>
<span id="no-cookies" class="hidden warning-login">You appear to have cookies disabled.<br/><br/></span>
<noscript><span class="warning-login"><?= SITE_NAME ?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br/><br/>
</noscript>
<?php
$banEpoch = strtotime($BannedUntil);
$now = time();
if ($banEpoch > $now) {
    $until = ($banEpoch - $now <= 60) ? 'a few moments' : ('another ' . time_diff($BannedUntil));
?>
    <span class="warning-login"><?= isset($Err) ? "$Err<br />" : '' ?>You are banned from logging in for <?= $until ?>.</span>
<?php } else { ?>
    <form class="auth_form" name="login" id="loginform" method="post" action="login.php?act=2fa">
<?php if (isset($Err)) { ?>
            <span class="warning-login"><?= $Err ?><br/><br/></span>
<?php
}
if ($Attempts > 0) { ?>
<p><strong>WARNING:</strong> Incorrect username/password details will increase the time<br >you are blocked from logging in.</p>
<?php } ?>
<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div style="width: 100%">
<div style="width: 35%; margin: auto;">
        <table class="layout">
            <tr>
                <td>2FA&nbsp;Key</td>
                <td colspan="2">
                    <input type="text" name="2fa" id="2fa" class="inputtext" required="required"
                           maxlength="6" pattern="[0-9]{6}" autofocus="autofocus" placeholder="Two-factor Auth Key"/>
                </td>
            </tr>

            <tr>
                <td></td>
                <td><input type="submit" name="login" value="Log in" class="submit"/></td>
            </tr>
        </table>
    </form>
    <a href="login.php?act=2fa_recovery" class="tooltip" title="Use 2FA Backup Code">Use a 2FA backup key?</a>
</div>
</div>
<?php } /* $BannedUntil in the past */ ?>

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
