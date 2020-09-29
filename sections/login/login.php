<?php View::show_header(); ?>
<span id="no-cookies" class="hidden warning-login">You appear to have cookies disabled.<br /><br /></span>
<noscript><span class="warning-login"><?=SITE_NAME?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br /><br /></noscript>

<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div class="main">
<div class="auth">
<?php
$banEpoch = strtotime($BannedUntil);
$delta = $banEpoch - time();
if ($delta > 0) {
?>
    <div class="warning-login"><?= isset($Err) ? "$Err<br />" : '' ?>You are blocked from logging in<br />for <?=
        $delta <= 60
            ? ('<span title="' . $delta . ' seconds">a few moments.</span>')
            : ('another ' . time_diff($BannedUntil) . '.')
?>
    </div>
<?php } else { ?>
<form class="auth_form" name="login" id="loginform" method="post" action="login.php">
<?php
    if (isset($Err) || isset($_GET['invalid2fa']) || $Attempts > 0) { ?>
<div class="warning-login">
<?php if (isset($Err)) { ?>
<?= $Err ?><br />
<?php
    }
    if (isset($_GET['invalid2fa'])) { ?>
You have entered an invalid two-factor authentication key, please login again.<br />
<?php
    }
    if ($Attempts > 0) {
?>
<strong>WARNING:</strong> Incorrect username/password details<br />will increase the time you are blocked from logging in.
<?php } ?>
</div>

<?php } /* $Err, invalid2fa, $Attempts */ ?>
    <div>
    <label for="username">Username</label>
    <input type="text" name="username" id="username" class="inputtext" required="required" maxlength="20" pattern="[A-Za-z0-9_?\.]{1,20}" autofocus="autofocus" placeholder="Username" />
    </div>
    <div>
    <label for="password">Password</label>
    <input type="password" name="password" id="password" class="inputtext" required="required" pattern=".{6,}" placeholder="Password" />
    </div>
    <div>
    <label title="Keep me logged in" for="keeplogged">Persistent</label>
    <input title="Keep me logged in" type="checkbox" id="keeplogged" name="keeplogged" value="1"<?=(isset($_REQUEST['keeplogged']) && $_REQUEST['keeplogged']) ? ' checked="checked"' : ''?> />
    </div>
    <div>
    <input type="submit" name="login" value="Log in" class="submit" />
    </div>
    <a href="login.php?act=recover" class="tooltip" title="Recover your password">Password recovery</a>
</form>
<?php } ?>
</div>
</div>
<script type="text/javascript">
cookie.set('cookie_test', 1, 1);
if (cookie.get('cookie_test') != null) {
    cookie.del('cookie_test');
} else {
    $('#no-cookies').gshow();
}
window.onload = function() {document.getElementById("username").focus();};
</script>
<?php
View::show_footer();
