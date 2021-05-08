<?php

View::show_header('Create a wiki article');
echo $Twig->render('wiki/article.twig', [
    'action'     => 'create',
    'body'       => new Gazelle\Util\Textarea('body', '', 92, 20),
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'edit'       => 0,
    'read'       => 0,
    'viewer'     => new Gazelle\User($LoggedUser['ID']),
]);
View::show_footer();
