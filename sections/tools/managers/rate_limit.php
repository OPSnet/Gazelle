<?php

if (!($Viewer->permittedAny('admin_rate_limit_view', 'admin_rate_limit_manage'))) {
    error(403);
}

$PRL = new Gazelle\Manager\PermissionRateLimit;
if ($_POST) {
    authorize();
    if (isset($_POST['task'])) {
        $remove = array_filter($_POST, fn($x) => preg_match('/^remove-\d+$/', $x), ARRAY_FILTER_USE_KEY);
        if (is_array($remove) && count($remove) == 1) {
            $PRL->remove(trim(array_keys($remove)[0], 'remove-'));
        } elseif ($_POST['task'] === 'add') {
            $val = new Gazelle\Util\Validator;
            $val->setFields([
                ['class', '1', 'number', 'class must be set'],
                ['factor', '1', 'number', 'factor must be set (usually, a number larger than 1.0)', ['minlength' => 1, 'allowperiod' => true]],
                ['overshoot', '1', 'number', 'overshoot must be set', ['minlength' => 1]],
            ]);
            if (!$val->validate($_POST)) {
                error($val->errorMessage());
            }
            $PRL->save($_POST['class'], $_POST['factor'], $_POST['overshoot']);
        } else {
            error(403);
        }
    }
}

$privMan = new Gazelle\Manager\Privilege;
echo $Twig->render('admin/rate-limiting.twig', [
    'class_list' => (new Gazelle\Manager\User)->classList(),
    'priv_list'  => $privMan->privilegeList(),
    'rate_list'  => $PRL->list(),
    'viewer'     => $Viewer,
]);
