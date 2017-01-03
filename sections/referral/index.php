<?php

// redirect if referrals are currently closed
if (!OPEN_EXTERNAL_REFERRALS) {

    include('closed.php');
    die();
}

include(SERVER_ROOT."/classes/referral.class.php");
$Referral = new Referral();

include('referral_step_1.php');

?>
