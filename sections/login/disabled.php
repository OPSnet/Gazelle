<?php
View::show_header('Disabled');
?>
<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>
<script type="text/javascript">
function toggle_visibility(id) {
    var e = document.getElementById(id);
    if (e.style.display === 'block') {
        e.style.display = 'none';
    } else {
        e.style.display = 'block';
    }
}
</script>

<?php
if (isset($_POST['email']) && FEATURE_EMAIL_REENABLE) {
    // Handle auto-enable request
    if ($_POST['email'] != '') {
        $Output = AutoEnable::new_request($_POST['username'], $_POST['email']);
    } else {
        $Output = "Please enter a valid email address.";
    }
?>
<div style="width: 100%">
<div style="width: 60%; margin: 0 auto;">
<?= $Output ?>
<br /><br /><a href='login.php?action=disabled'>Back</a>
</div>
</div>

<?php } else { ?>

<p class="warning-login">
Your account has been disabled.<br />
This is either due to inactivity or rule violation(s).</p>

<div style="width: 100%">
<div style="width: 65%; margin: 0 auto;">

<?php if (FEATURE_EMAIL_REENABLE) { ?>
<p>If you believe your account was in good standing and was disabled
for inactivity, you may request it be re-enabled via email using
the form below.  Please note that you will need access to the email
account associated with your account at <?= SITE_NAME ?> if you
cannot do so, please see below.</p>

<form action="" method="POST">
    <input type="email" class="inputtext" placeholder="Email Address" name="email" required /> <input type="submit" value="Enable" />
    <input type="hidden" name="username" value="<?= $_COOKIE['username']?>" />
</form>
<br />

<?php } ?>

<p>If you are unsure why your account is disabled, or you wish to
discuss this with staff, come to our IRC network at
<b><?= BOT_SERVER ?></b> and join the
<b><?= BOT_DISABLED_CHAN ?></b>&nbsp;channel. Use of Mibbit is
<i>not</i> recommended. If nobody is online when you join, after a
time your session will be disconnected and you will never receive
a reply and will have to connect again. It could be a while before
you manage to move forward.</p>

<p><strong>Be honest.</strong> At this point, lying will get you
nowhere.</p>

<strong>Before joining the disabled channel, please read our <span
style="color: gold;">Golden Rules</span> right
<a style="color: #1464F4;" href="#" onclick="toggle_visibility('golden_rules')">here</a>.</strong>

<br /><br />

<div id="golden_rules" class="rule_summary" style="width: 35%; font-weight: bold; display: none; text-align: left;">
<?= G::$Twig->render('rules/golden.twig', [ 'site_name' => SITE_NAME ]) ?>
</div>

</div>
</div>

<?php
}

View::show_footer();
