<?php
if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}
View::show_header();
?>

<div id="logo">
<img src="static/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" />
</div>

<div class="main">
<div class="para">Orpheus with his lute made trees<br />
And the mountain tops that freeze<br />
Bow themselves when he did sing:<br />
To his music plants and flowers<br />
Ever sprung; as sun and showers<br />
There had made a lasting spring.</div>

<div class="para">Every thing that heard him play,<br />
Even the billows of the sea,<br />
Hung their heads and then lay by.<br />
In sweet music is such art,<br />
Killing care and grief of heart<br />
Fall asleep, or hearing, die.</div>
</div>

<div class="actions">
    <span class="action-bar">
    <a href="/login.php">Enter</a>
<?php if (OPEN_REGISTRATION) { ?>
    <a title="Obtain an account by supplying a valid email address" href="register.php">Register</a>
<?php } ?>
<?php if (OPEN_EXTERNAL_REFERRALS) { ?>
    <a title="Obtain an account by proving your membership on a site we trust" href="referral.php">Referral</a>
<?php } ?>
<?php if (RECOVERY) { ?>
    <a title="Obtain a new account by proving your membership on the previous site" href="recovery.php">Recovery</a>
<?php } ?>
    </span>
</div>
<?php
View::show_footer();
