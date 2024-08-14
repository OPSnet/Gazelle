<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (MONERO_DONATION_ADDRESS) {
    $moneroDonation = new Gazelle\Donate\Monero();
    $moneroAddr = $moneroDonation->address($Viewer->id());
} else {
    $moneroAddr = null;
}

if (BITCOIN_DONATION_XYZPUB) {
    $btcDonation = new Gazelle\Donate\Bitcoin();
    $btcAddr = $btcDonation->address($Viewer->id());
} else {
    $btcAddr = null;
}

echo $Twig->render('donation/index.twig', [
    'bitcoin_address' => $btcAddr,
    'monero_address'  => $moneroAddr,
]);
