<?php
/** @phpstan-var \Gazelle\User $Viewer */

use Gazelle\Util\Arrays;

if (!$Viewer->permitted('admin_manage_navigation')) {
    error(403);
}

authorize();

$manager = new Gazelle\Manager\UserNavigation();

if ($_POST['submit'] == 'Delete') {
    $id = (int)($_POST['id'] ?? 0);
    $control = $manager->findById($id);
    if (is_null($control)) {
        error(404);
    }
    $control->remove();
} else {
    $validator = new Gazelle\Util\Validator();
    $validator->setFields([
        ['tag',       true, 'string', 'The key must be set, and has a max length of 20 characters', ['maxlength' => 20]],
        ['title',     true, 'string', 'The title must be set, and has a max length of 50 characters', ['maxlength' => 50]],
        ['target',    true, 'string', 'The target must be set, and has a max length of 200 characters', ['maxlength' => 200]],
        ['tests',     false, 'string', 'The tests are optional, and have a max length of 200 characters', ['maxlength' => 200]],
        ['testuser',  true, 'checkbox', ''],
        ['mandatory', true, 'checkbox', ''],
        ['default',   true, 'checkbox', ''],
    ]);
    if (!$validator->validate($_POST)) {
        error($validator->errorMessage());
    }

    if ($_POST['submit'] == 'Create') {
        $control = $manager->create(
            trim($_POST['tag']),
            trim($_POST['title']),
            trim($_POST['target']),
            trim($_POST['tests']),
            $_POST['testuser'] == 'on',
            $_POST['mandatory'] == 'on',
            $_POST['default'] == 'on',
        );
    } elseif ($_POST['submit'] == 'Edit') {
        $id = (int)($_POST['id'] ?? 0);
        $control = $manager->findById($id);
        if (is_null($control)) {
            error(404);
        }
        $control->setField('tag', trim($_POST['tag']))
            ->setField('target', trim($_POST['target']))
            ->setField('tests', trim($_POST['tests']))
            ->setField('title', trim($_POST['title']))
            ->setField('test_user', $_POST['testuser'] == 'on')
            ->setField('mandatory', $_POST['mandatory'] == 'on')
            ->setField('initial', $_POST['default'] == 'on')
            ->modify();
    } else {
        error(0);
    }
}

header("Location: {$control->location()}");
