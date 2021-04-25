<?php
if (!check_perms('admin_manage_permissions') && !check_perms('users_mod')) {
    error(403);
}

$siteOption = new Gazelle\Manager\SiteOption;

if (check_perms('admin_manage_permissions') && isset($_POST['submit'])) {
    authorize();

    $name = trim($_POST['name']);
    $value = trim($_POST['value']);
    $comment = trim($_POST['comment']);

    if ($_POST['submit'] == 'Delete') {
        $siteOption->remove($name);
    } else {
        $Val = new Gazelle\Util\Validator;
        $Val->setFields([
            ['name', '1', 'regex', 'The name must be alphanumeric and may contain dashes or underscores. No spaces are allowed.', ['regex' => '/^[a-z][-_a-z0-9]{0,63}$/i']],
            ['value', '1', 'string', 'You must specify a value for the option.'],
            ['comment', '1', 'string', 'You must specify a comment for the option.'],
        ]);
        if (!$Val->validate($_POST)) {
            error($Val->errorMessage());
        }

        if ($_POST['submit'] == 'Edit') {
            $siteOption->modify($_POST['id'], $name, $value, $comment);
        } else {
            $siteOption->create($name, $value, $comment);
        }
    }
}

View::show_header('Site Options');
echo $Twig->render('admin/site-option.twig', [
    'auth'     => $LoggedUser['AuthKey'],
    'is_admin' => check_perms('admin_manage_permissions'),
    'list'     => $siteOption->list(),
]);
View::show_footer();
