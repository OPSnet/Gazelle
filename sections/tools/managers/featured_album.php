<?php

use Gazelle\Enum\FeaturedAlbumType;
use Gazelle\Enum\LeechType;
use Gazelle\Enum\LeechReason;

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$tgMan   = new Gazelle\Manager\TGroup;
$torMan  = new Gazelle\Manager\Torrent;
$manager = new Gazelle\Manager\FeaturedAlbum;

if (isset($_GET['unfeature'])) {
    authorize();
    switch ($_GET['unfeature']) {
        case 'aotm':
            $manager->findByType(FeaturedAlbumType::AlbumOfTheMonth)?->unfeature();
            break;
        case 'showcase':
            $manager->findByType(FeaturedAlbumType::Showcase)?->unfeature();
            break;
        default:
            error(403);
    }
    header('Location: tools.php?action=featured_album');
    exit;
}

$size = (int)($_POST['size'] ?? NEUTRAL_LEECH_THRESHOLD);
$unit = trim($_POST['unit'] ?? NEUTRAL_LEECH_UNIT);

$leechType   = $torMan->lookupLeechType($_POST['leech_type'] ?? LeechType::Free->value);
$leechReason = $torMan->lookupLeechReason($_POST['leech_reason'] ?? LeechReason::AlbumOfTheMonth->value);

if (isset($_POST['groupid'])) {
    authorize();

    // match an ID or the last id in a URL (e.g. torrent group)
    $tgroup = null;
    if (preg_match('/(\d+)\s*$/', $_POST['groupid'], $match)) {
        $tgroup = $tgMan->findById((int)$match[1]);
    }
    if (is_null($tgroup)) {
        error('You did not enter a valid group ID');
    }
    if (empty($_POST['body'])) {
        error('You did not provide any text for the feature');
    }
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        error('You did not provide a title for the front page announcement');
    }

    if ($leechType === LeechType::Normal || !isset($_POST['neutral'])) {
        $threshold = 0;
    } else {
        if (!$size || !in_array($unit, ['k', 'm', 'g'])) {
            error('Invalid size or units for freeleech');
        }
        $threshold = get_bytes("$size$unit");
    }

    $featureType = $leechReason == LeechReason::Showcase
        ? FeaturedAlbumType::Showcase
        : FeaturedAlbumType::AlbumOfTheMonth;

    $featuredAlbum = $manager->create(
        featureType: $featureType,
        news:        new Gazelle\Manager\News,
        tgMan:       $tgMan,
        torMan:      $torMan,
        tracker:     new Gazelle\Tracker,
        tgroup:      $tgroup,
        forumThread: (new Gazelle\Manager\ForumThread)->create(
            forum:  new Gazelle\Forum($featureType->forumId()),
            userId: $Viewer->id(),
            title:  $tgroup->text(),
            body:   trim($_POST['body']),
        ),
        leechType: $leechType,
        threshold: $threshold,
        title:     $title,
        user:      $Viewer,
    );

    header("Location: " . $featuredAlbum->location());
    exit;
}

echo  $Twig->render('tgroup/feature.twig', [
    'body'         => new Gazelle\Util\Textarea('body', '', 80, 20),
    'current'      => [
        'aotm'     => $manager->findByType(FeaturedAlbumType::AlbumOfTheMonth),
        'showcase' => $manager->findByType(FeaturedAlbumType::Showcase),
    ],
    'feature'      => [
        'aotm'     => FeaturedAlbumType::AlbumOfTheMonth,
        'showcase' => FeaturedAlbumType::Showcase,
    ],
    'leech_type'   => $torMan->leechTypeList(),
    'leech_reason' => [
        LeechReason::AlbumOfTheMonth,
        LeechReason::Showcase,
    ],
    'size'         => $size,
    'unit'         => $unit,
    'viewer'       => $Viewer,
]);
