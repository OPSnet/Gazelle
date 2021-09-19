<?php
if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}
echo $Twig->render('index/public.twig');
