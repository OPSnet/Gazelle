<?php

if (!$Viewer->permittedAny('admin_rate_limit_view', 'admin_rate_limit_manage')) {
    error(403);
}

$limiter = new Gazelle\Manager\UserclassRateLimit;
if ($_POST) {
    authorize();
    $remove = array_filter($_POST, fn($x) => preg_match('/^remove-\d+$/', $x), ARRAY_FILTER_USE_KEY);
    if (count($remove) == 1) {
        $limiter->remove((int)trim(array_keys($remove)[0], 'remove-'));
    } elseif ($_POST['task'] === 'add') {
        $val = new Gazelle\Util\Validator;
        $val->setFields([
            ['class', true, 'number', 'class must be set'],
            ['factor', true, 'number', 'factor must be set (usually, a number larger than 1.0)', ['minlength' => 1, 'allowperiod' => true]],
            ['overshoot', true, 'number', 'overshoot must be set', ['minlength' => 1]],
        ]);
        if (!$val->validate($_POST)) {
            error($val->errorMessage());
        }
        $limiter->save($_POST['class'], $_POST['factor'], $_POST['overshoot']);
    } else {
        error(403);
    }
}

echo $Twig->render('admin/rate-limiting.twig', [
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'priv_list'  => (new Gazelle\Manager\Privilege)->privilegeList(),
    'rate_list'  => $limiter->list(),
    'viewer'     => $Viewer,
]);
