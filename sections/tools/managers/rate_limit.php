<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_rate_limit_view', 'admin_rate_limit_manage')) {
    Error403::error();
}

$limiter = new Manager\UserclassRateLimit();
if ($_POST) {
    authorize();
    $remove = array_key_extract_suffix('remove-', $_POST);
    if (count($remove) == 1) {
        $limiter->remove($remove[0]);
    } elseif ($_POST['task'] === 'add') {
        $val = new Util\Validator();
        $val->setFields([
            ['class', true, 'number', 'class must be set'],
            ['factor', true, 'number', 'factor must be set (usually, a number larger than 1.0)', ['minlength' => 1, 'allowperiod' => true]],
            ['overshoot', true, 'number', 'overshoot must be set', ['minlength' => 1]],
        ]);
        if (!$val->validate($_POST)) {
            Error400::error($val->errorMessage());
        }
        $limiter->save($_POST['class'], $_POST['factor'], $_POST['overshoot']);
    } else {
        Error403::error();
    }
}

echo $Twig->render('admin/rate-limiting.twig', [
    'class_list' => new Manager\User()->classList(),
    'priv_list'  => new Manager\Privilege()::privilegeList(),
    'rate_list'  => $limiter->list(),
    'viewer'     => $Viewer,
]);
