<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_contest')) {
    Error403::error();
}

$contestMan = new Manager\Contest();
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
        $contest->bonusPool()
            ->setField('contest', (int)$_POST['pool-contest'])
            ->setField('entry',   (int)$_POST['pool-entry'])
            ->setField('user',    (int)$_POST['pool-user'])
            ->modify();
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
    'intro'      => new Util\Textarea('description', $contest?->description() ?? '', 60, 8),
    'list'       => $contestMan->contestList(),
    'saved'      => $saved,
    'type'       => $contestMan->contestTypes(),
    'user_count' => new \Gazelle\Stats\Users()->enabledUserTotal(),
    'viewer'     => $Viewer,
]);
