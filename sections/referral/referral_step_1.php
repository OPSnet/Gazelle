<?php

// redirect if referrals are currently closed
if (!OPEN_EXTERNAL_REFERRALS) {

    include('closed.php');
    die();
}

// get service from post value
$Service = $_POST['service'];
// save service to session


// generate token
$Token = $Referral->generate_token();



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
        <h2>Step 1: Paste Your Code</h2>
        <br/>
        <p>Copy and paste the code below into the profile of your <?php echo $Service; ?> account. It can go anywhere in your profile body (commonly known as "Profile info 1") as long as it is in one piece.</p>
        <br/>
        <br/>
        <p id="referral-code"><?php echo $Token; ?></p>
        <br/>
        <br/>
        <p>Now, enter the username you use at <?php echo $Service; ?> exactly. This is critical in verifying your account.</p>
        <br/>
        <form name="referral_service" method="post" action="">
            <input type="text" name="username" /><label for="username">Username</label>
            <input type="text" name="email" /><label for="Email">Email Address</label>
            <input type="hidden" name="token" value="<?php echo $Token; ?>" />
            <input type="hidden" name="service" value="<?php echo $Service; ?>" />
            <br/>
            <br/>
            <input type="submit" name="submit" value="Verify" class="submit" />
        </form>

    </div>
<?php View::show_footer(); ?>