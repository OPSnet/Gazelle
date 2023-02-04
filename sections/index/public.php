<?php
if (!SHOW_PUBLIC_INDEX) { /** @phpstan-ignore-line */
    header('Location: login.php');
    exit;
}
echo $Twig->render('index/public.twig');
