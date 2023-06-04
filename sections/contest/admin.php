<?php

if (!$Viewer->permitted('admin_manage_contest')) {
    error(403);
}

$contestMan = new Gazelle\Manager\Contest;
$create     = isset($_GET['action']) && $_GET['action'] === 'create';
$saved      = false;

if (isset($_POST['cid'])) {
    authorize();
    $contest = $contestMan->findById((int)$_POST['cid']);
    $saved = $contest
        ->setField('banner',          trim($_POST['banner']))
        ->setField('contest_type_id', $_POST['type'])
        ->setField('date_begin',      $_POST['date_begin'])
        ->setField('date_end',        $_POST['date_end'])
        ->setField('description',     trim($_POST['description']))
        ->setField('display',         (int)$_POST['display'])
        ->setField('name',            trim($_POST['name']))
        ->modify();
    if ($contest->hasBonusPool()) {
        $affected = $contest->modifyBonusPool(
            contest: (int)$_POST['pool-contest'],
            entry:   (int)$_POST['pool-entry'],
            user:    (int)$_POST['pool-user'],
        );
    }
} elseif (isset($_POST['new'])) {
    authorize();
    $contest = $contestMan->create(
        banner:      trim($_POST['banner']),
        type:        (int)$_POST['type'],
        dateBegin:   $_POST['date_begin'],
        dateEnd:     $_POST['date_end'],
        description: trim($_POST['description']),
        display:     (int)$_POST['display'],
        hasPool:     isset($_POST['pool']),
        name:        trim($_POST['name']),
    );
} elseif (isset($_GET['id'])) {
    $contest = $contestMan->findById((int)$_GET['id']);
} elseif (!$create) {
    $contest = $contestMan->currentContest();
} else {
    $contest = null;
}

echo $Twig->render('contest/admin.twig', [
    'contest'    => $contest,
    'create'     => $create,
    'intro'      => new Gazelle\Util\Textarea('description', $contest?->description() ?? '', 60, 8),
    'list'       => $contestMan->contestList(),
    'saved'      => $saved,
    'type'       => $contestMan->contestTypes(),
    'user_count' => (new \Gazelle\Stats\Users)->enabledUserTotal(),
    'viewer'     => $Viewer,
]);
