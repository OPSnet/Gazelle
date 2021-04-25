<?php
View::show_header('Register');
echo $Val->generateJS('registerform');
?>
<script src="<?=STATIC_SERVER?>/functions/validate.js" type="text/javascript"></script>
<script src="<?=STATIC_SERVER?>/functions/password_validate.js" type="text/javascript"></script>

<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div style="width: 100%">
<div style="width: 45%; margin: auto;">
<form class="create_form" name="user" id="registerform" method="post" action="" onsubmit="return formVal();">
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php
if (empty($Sent)) {
    if (!empty($_REQUEST['invite'])) {
        echo '<input type="hidden" name="invite" value="'.display_str($_REQUEST['invite']).'" />'."\n";
    }
    if (!empty($Err)) {
?>
    <strong class="important_text"><?= $Err ?></strong><br /><br />
<?php
    }
    echo $Twig->render('login/create.twig', [
        'username'  => $_REQUEST['username'],
        'email'     => $_REQUEST['email'] ?? $InviteEmail,
        'readrules' => $_REQUEST['readrules'],
        'readwiki'  => $_REQUEST['readwiki'],
        'agereq'    => $_REQUEST['agereq'],
    ]);
} else { ?>
An email has been sent to the address that you provided. After you confirm your email address, you will be able to log into your account.
If you do not receive a response within a couple of hours, you may try contacting staff on IRC. Join <tt>irc.orpheus.network</tt>,
channel <tt>#disabled</tt>. Depending on the timezone you may have to wait, but you will receive a response.
<?php if ($NewInstall) { ?>
Since this is a new installation, you can log in directly without having to confirm your account.
<?php
    }
}
?>
</div>
</form>
</div>
</div>
<?php
View::show_footer();
