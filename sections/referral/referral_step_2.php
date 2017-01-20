<?php

// redirect if referrals are currently closed
if (!OPEN_EXTERNAL_REFERRALS) {

    include('closed.php');
    die();
}

// get needed information from post values
$Service = $_POST['service'];
$Email = $_POST['email'];

// let's sanitize the email before we continue
$SanitizedEmail = filter_var($Email, FILTER_SANITIZE_EMAIL);
if (!filter_var($SanitizedEmail, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['verify_error'] = "Invalid Email Address, Please Try Again";
}

// check post token vs session
if ($_POST['token'] !== $_SESSION['referral_token']) {
    die('Invalid Token, please try again.');
}

// verify external user with token match
$Verify = $Referral->verify($Service, $_POST['username']);
if ($Verify === TRUE) {
    // success
    $Invited = $Referral->create_invite($Service, $SanitizedEmail, $_POST['username']);
} else {
    $error = $_SESSION['verify_error'];
}




View::show_header('External Tracker Referrals');
?>
    <style>
        * {
            margin: initial;
            padding: initial;
        }
        ol {
            -webkit-margin-before: 1em;
            -webkit-margin-after: 1em;
            -webkit-padding-start: 40px;
        }
        label {
            margin-left: 15px;
        }
        #referral-code {
            color: #f5f5f5;
            padding: 10px;
            background-color: #151515;
            text-align: center;
        }
    </style>
    <div style="width: 500px; text-align: left">
        <h1>External Tracker Referrals</h1>
        <br/>
        <p>Here you are able to gain access to <?php echo SITE_NAME; ?> by verifying that you are a member of another private tracker that we trust.</p>
        <br/>
        <h4>The process is as follows:</h4>
        <br/>
        <ol>
            <li>Choose a tracker from the list that you're a member of.</li>
            <li><?php echo SITE_NAME; ?> will generate a string of characters that you will place in the body of your profile at the tracker of your choice.</li>
            <li>Paste the character string anywhere in the body of your profile.</li>
            <li>Enter your username and <?php echo SITE_NAME; ?> will verify your membership and issue an invite code to you.</li>
            <li>Join <?php echo SITE_NAME; ?>.</li>
            <li><strong>???</strong></li>
            <li>Profit.</li>
        </ol>
        <br/>
        <h2>Step 2: Join <?php echo SITE_NAME; ?></h2>
        <br/>
        <?php if (!$Verify || $error): ?>
            <h3>There was an error verifying your account at <?php echo $Service; ?>. Please refresh the page and try again.</h3>
            <p><?php echo $error; ?></p>
        <?php else: ?>
            <h3>Congratulations, you have verified your account at <?php echo $Service; ?>. You have been issued an email that has been sent to the email address you provided. Be sure to check your spam folder, and welcome to <?php echo SITE_NAME; ?>!</h3
        <?php endif; ?>
        <br/>
        <br/>

    </div>
<?php View::show_footer(); ?>