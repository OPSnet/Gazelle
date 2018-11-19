<?php

    // redirect if referrals are currently closed
if (!OPEN_EXTERNAL_REFERRALS) {
    include('closed.php');
    exit;
}

    $Referral = new Referral();
    $AvailableServices = $Referral->services_list();

    // head to step 2 if are ready to verify an account at the external service
if (!empty($_POST['username']) && $_POST['submit'] === 'Verify') {
    include('referral_step_2.php');
    exit;
}

    // head to step 1 if we've selected a tracker, and verified that it's still up
if (in_array($_POST['service'], $AvailableServices)) {
    include('referral_step_1.php');
    exit;
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
            <li>Paste the character string anywhere in the body of your profile and save it.</li>
            <li>Enter your username and <?php echo SITE_NAME; ?> will verify your membership and issue an invite code to you.</li>
            <li>Join <?php echo SITE_NAME; ?>!</li>
        </ol>

    <?php if (!empty($AvailableServices)) : ?>
        <br/>
        <h2>Choose a Tracker:</h2>
        <br/>
        <form name="referral_service" method="post" action="">
            <?php
            foreach ($AvailableServices as $Service) {
                echo '<input type="radio" name="service" value="' . $Service . '"/><label for="' . $Service . '">  ' . $Service . '</label><br/><br/>';
            } ?>
            <br/>
            <input type="submit" name="submit" value="Submit" class="submit" />
        </form>
    <?php else : ?>
        <br/>
        <h2>Sorry, we aren't accepting external tracker referrals at this time. Please try again later.</h2>
        <br/>
    <?php endif; ?>
    </div>
<?php View::show_footer(); ?>