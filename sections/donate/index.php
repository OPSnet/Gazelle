<?php

if (MONERO_DONATION_ADDRESS) {
    $moneroDonation = new Gazelle\Donate\Monero;
    $moneroAddr = $moneroDonation->address($Viewer->id());
} else {
    $moneroAddr = null;
}

echo $Twig->render('donation/index.twig', [
    'monero_address'         => $moneroAddr,
]);
