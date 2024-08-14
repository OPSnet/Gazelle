<?php
/** @phpstan-var \Twig\Environment $Twig */

echo $Twig->render('user/2fa/remove.twig', [
    'bad' => isset($_GET['invalid']),
]);
