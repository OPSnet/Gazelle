<?php
if ($Viewer) {
    header("Location: index.php");
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start(['read_and_close' => true]);
}
?>

<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<?php
// redirect if referrals are currently closed, or no partner sites
$ReferralManager = new Gazelle\Manager\Referral;
$Accounts = $ReferralManager->getActiveAccounts();

if (!OPEN_EXTERNAL_REFERRALS || !count($Accounts) || $ReferralManager->readOnly) {
    View::show_header("Referrals are closed");
?>
<div class="thin" style="text-align: center;">
    <strong class="important_text">Sorry, <?= SITE_NAME ?> is currently not accepting referrals.</strong>
</div>
<?php
    View::show_footer();
    exit;
}

View::show_header('External Tracker Referrals');
?>

<br />
<div class="referral">
    <h1>External Tracker Referrals</h1>
    <p>Here you may create an account on <?= SITE_NAME ?> by verifying that you are a member of another private tracker that we trust.</p>
    <h4>The process is as follows:</h4>
    <ol>
        <li>Choose a tracker that you are a member of from the following list:
            <?= implode(', ', REFERRAL_SITES) ?>.</li>
        <li><?= SITE_NAME ?> will generate a string of characters that you will place in the body of your profile at the tracker of your choice.</li>
        <li>Paste the character string anywhere in the body of your profile and save it.</li>
        <li>Enter your username and <?= SITE_NAME ?> will verify your membership and issue an invite code to you.</li>
        <li>Join <?= SITE_NAME ?>!</li>
    </ol>

<?php if (empty($_POST['action'])) { ?>
    <h2>Choose a Tracker:</h2>
    <div class="center">
        <form name="referral_service" method="post" action="">
<?php
    foreach ($Accounts as $Account) {
            $ID = "site" . $Account["ID"];
?>
                <div>
                    <input id="<?=$ID?>" type="radio" name="service" value="<?=$Account["ID"]?>"/>
                    <label for="<?=$ID?>"><?=$Account["Site"]?></label>
                </div>
<?php } ?>
            <br/>
            <input type="hidden" name="action" value="account">
            <input type="submit" name="submit" value="Submit" class="submit" />
        </form>
    </div>
<?php
} elseif ($_POST['action'] == 'account') {
    $Token = $ReferralManager->generateToken();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['referral_token'] = $Token;
    session_write_close();
    $Account = $ReferralManager->getAccount($_POST['service']);
?>
    <br/>
    <h2>Step 2: Paste Your Code</h2>
    <br/>
    <p>Copy and paste the code below into the profile of your <?=$Account["Site"]?> account. It can go anywhere in your profile body (commonly known as "Profile info 1") as long as it is in one piece.</p>
    <br/>
    <p id="referral-code"><?=$Token?></p>
    <br/>
    <p>Enter the <?=$Account["UserIsId"] ? "user id" : "username"?> you use at <?=$Account["Site"]?> exactly as it appears on the site. This is critical in verifying your account.</p>
    <br/>
    <div class="center">
        <form name="referral_service" method="post" action="">
        <label for="username"><?=$Account["UserIsId"] ? "User Id" : "Username"?></label><input type="text" name="username" /><br />
            <label for="Email">Email Address</label><input type="text" name="email" />
            <input type="hidden" name="token" value="<?=$Token?>" />
            <input type="hidden" name="service" value="<?=$Account["ID"]?>" />
            <input type="hidden" name="action" value="verify" />
            <br/>
            <br/>
            <input type="submit" name="submit" value="Verify" class="submit" />
            <br/>
            <br/>
        </form>
    </div>
</div>
<?php
} elseif ($_POST['action'] == 'verify') {
    $Token = $_SESSION['referral_token'];
    if ($Token != $_POST['token']) {
        header("Location: referral.php");
    }

    $Email = (string)filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $Error = false;
    $Invite = false;
    if (!preg_match(EMAIL_REGEXP, $Email)) {
        $Error = "Invalid email address.";
    } else {
        $Account = $ReferralManager->getFullAccount($_POST['service']);
        if ($Account["UserIsId"] && !preg_match('/^\d+$/', $_POST['username'])) {
            $Error = "You appear to have entered a username instead of your user id.";
        } else {
            $Verified = $ReferralManager->verifyAccount($Account, $_POST['username'], $Token);
            if ($Verified !== true) {
                $Error = $Verified;
            } else {
                [$Success, $Invite] = $ReferralManager->generateInvite($Account, $_POST['username'], $Email);
                if (!$Success) {
                    $Error = $Invite;
                } elseif ($Invite === false) {
                    $Error = "Failed to generate invite.";
                }
            }
        }
    }
?>
    <br />
    <h2>Step 2: Join</h2>
    <br />
<?php
    if (isset($Account)) {
        if ($Error) {
?>
    <h3>There was an error verifying your account at <?=$Account["Site"]?>. Please refresh the page and try again.</h3>
    <br />
    <p><?=$Error?></p>
<?php
    } elseif (REFERRAL_SEND_EMAIL) {
?>
            <h3>Congratulations, you have verified your account at <?=$Account["Site"]?>. We have sent you an email to the address you specified. Make sure to check your spam folder! Welcome to <?=SITE_NAME?>!</h3>
<?php } else { ?>
            <h3>Congratulations, you have verified your account at <?=$Account["Site"]?>. <a href=register.php?invite=<?=$Invite?>">Click here</a> to register. Welcome to <?=SITE_NAME?></h3>
<?php } ?>
</div>
<?php
    }
}
View::show_footer();
