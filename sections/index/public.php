<?php
/** @phpstan-var \Twig\Environment $Twig */

if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}
echo $Twig->render('index/public.twig', [
    'new' => (new Gazelle\Stats\Users())->enabledUserTotal() == 0,
]);
