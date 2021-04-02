<?php
if (!SHOW_PUBLIC_INDEX) {
    header('Location: login.php');
    exit;
}
View::show_header();
echo G::$Twig->render('index/public.twig');
View::show_footer();
