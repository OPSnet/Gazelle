<?php

use Gazelle\Util\Paginator;

if (!isset($_POST['action'])) {
    $userId = (int)($_GET['userid'] ?? $LoggedUser['ID']);
    if (!$userId) {
        error(404);
    }
    if ($LoggedUser['ID'] != $userId && !check_perms('users_view_ips')) {
        error(403);
    }
} else {
    $userId = (int)$_POST['userid'];
    if (!$userId) {
        error(404);
    }
}

$user = (new Gazelle\Manager\User)->findById($userId);
if (!$userId) {
    error(404);
}

$seedbox = new Gazelle\Seedbox($userId);
if (isset($_POST['action'])) {
    $source = $_POST['source'];
    $target = $_POST['target'];
    $union = trim($_POST['view']) === 'union' ? 'union' : 'exclude';
    if ($source && $source === $target) {
        $err = "Cannot compare a location with itself!";
    }
    header("Location:" . $_SERVER['REQUEST_URI'] . "&source={$source}&target={$target}&view={$union}&page=1");
    exit;
}

if (isset($_GET['source']) && isset($_GET['target'])) {
    $source = $_GET['source'];
    $target = $_GET['target'];
    $union = trim($_GET['view']) === 'union' ? true : false;
    $seedbox->setSource($source)
        ->setTarget($target)
        ->setUnion($union);
}

View::show_header($user->username() . ' &rsaquo; Seedboxes &rsaquo; View');
?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($userId, false, false, false)?> &rsaquo; Seedboxes</h2>
        <div class="linkbox">
            <a href="user.php?action=seedbox&amp;userid=<?= $userId ?>" class="brackets">Configure</a>
            <a href="user.php?action=seedbox-view&amp;userid=<?= $userId ?>" class="brackets">View</a>
        </div>
    </div>
<?php if (isset($err)) { ?>
<div class="pad box">
    <?= $err ?>
</div>
<?php
} elseif (isset($source) && isset($target)) {
    $paginator = new Paginator(TORRENTS_PER_PAGE, (int)$_GET['page']);
    $list = $seedbox->torrentList($paginator, new Gazelle\Manager\Torrent, new Gazelle\Manager\TorrentLabel);

    echo $paginator->linkbox();

    echo G::$Twig->render('seedbox/report.twig', [
        'list'   => $list,
        'mode'   => $union ? 'union' : 'exclude',
        'source' => $seedbox->name($source),
        'target' => $seedbox->name($target),
    ]);

    echo  $paginator->linkbox();
}

echo G::$Twig->render('seedbox/view.twig', [
    'auth'   => $LoggedUser['AuthKey'],
    'host'   => $seedbox->hostList(),
    'mode'   => $union ? 'union' : 'exclude',
    'source' => $source,
    'target' => $target,
    'userid' => $userId,
]);
?>
</div>
<?php
View::show_footer();
