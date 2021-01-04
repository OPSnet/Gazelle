<?php
View::show_header('Two-factor Authentication Validation');

echo G::$Twig->render('login/2fa-validate.twig', []);

View::show_footer();
