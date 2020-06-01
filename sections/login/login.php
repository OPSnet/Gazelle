<?php View::show_header(); ?>
<span id="no-cookies" class="hidden warning">You appear to have cookies disabled.<br /><br /></span>
<noscript><span class="warning"><?=SITE_NAME?> requires JavaScript to function properly. Please enable JavaScript in your browser.</span><br /><br /></noscript>

<div id="logo">
<a href="/"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div class="main">
<?php if (strtotime($BannedUntil) < time()) { ?>
<div class="auth">
<form class="auth_form" name="login" id="loginform" method="post" action="login.php">
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
<span class="warning"><?=$Err?><br /><br /></span>
<?php
}
if ($Attempts > 0) { ?>
You have <span class="info"><?=(6 - $Attempts)?></span> attempts remaining.<br /><br />
<strong>WARNING:</strong> You will be banned for 6 hours after your login attempts run out!<br /><br />
<?php
}
if (isset($_GET['invalid2fa'])) { ?>
<span class="warning">You have entered an invalid two-factor authentication key. Please login again.</span>
<?php } ?>
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
<?php
} else {
?>
<span class="warning">You are banned from logging in for another <?=time_diff($BannedUntil)?>.</span>
<?php
}

?>
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
