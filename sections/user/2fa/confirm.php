<?php

echo $Twig->render('user/2fa/remove.twig', [
    'bad' => isset($_GET['invalid']),
]);
