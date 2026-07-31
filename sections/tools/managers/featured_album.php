<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

$tgMan   = new Manager\TGroup();
$torMan  = new Manager\Torrent();
$manager = new Manager\FeaturedAlbum();

if (isset($_GET['unfeature'])) {
    authorize();
    match ($_GET['unfeature']) {
        'aotm'     => $manager->findByType(Enum\FeaturedAlbumType::AlbumOfTheMonth)?->unfeature(),
        'showcase' => $manager->findByType(Enum\FeaturedAlbumType::Showcase)?->unfeature(),
        default    => Error403::error(),
    };
    header('Location: tools.php?action=featured_album');
    exit;
}

$size = (int)($_POST['size'] ?? NEUTRAL_LEECH_THRESHOLD);
$unit = trim($_POST['unit'] ?? NEUTRAL_LEECH_UNIT);

$leechType   = Enum\LeechType::tryFrom($_POST['leech_type'] ?? '') ?? Enum\LeechType::Free;
$leechReason = Enum\LeechReason::tryFrom($_POST['leech_reason'] ?? '') ?? Enum\LeechReason::AlbumOfTheMonth;

if (isset($_POST['groupid'])) {
    authorize();

    // match an ID or the last id in a URL (e.g. torrent group)
    $tgroup = null;
    if (preg_match('/(\d+)\s*$/', $_POST['groupid'], $match)) {
        $tgroup = $tgMan->findById((int)$match[1]);
    }
    if (is_null($tgroup)) {
        Error400::error('You did not enter a valid group ID');
    }
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        Error400::error('You did not provide any text for the feature');
    }
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        Error400::error('You did not provide a title for the front page announcement');
    }

    if ($leechType === Enum\LeechType::Normal || !isset($_POST['neutral'])) {
        $threshold = 0;
    } else {
        if (!$size || !in_array($unit, ['k', 'm', 'g'])) {
            Error400::error('Invalid size or units for freeleech');
        }
        $threshold = get_bytes("$size$unit");
    }

    $featureType = $leechReason === Enum\LeechReason::Showcase
        ? Enum\FeaturedAlbumType::Showcase
        : Enum\FeaturedAlbumType::AlbumOfTheMonth;

    $featuredAlbum = $manager->create(
        featureType: $featureType,
        news:        new Manager\News(),
        tgMan:       $tgMan,
        torMan:      $torMan,
        threadMan:   new Manager\ForumThread(),
        tracker:     new Tracker(),
        tgroup:      $tgroup,
        forum:       new Forum($featureType->forumId()),
        leechType:   $leechType,
        threshold:   $threshold,
        title:       $title,
        body:        $body,
        pitch:       trim($_POST['pitch'] ?? '') ?: 'Discuss this post',
        user:        $Viewer,
    );

    header("Location: " . $featuredAlbum->location());
    exit;
}

echo  $Twig->render('admin/feature-album.twig', [
    'body'         => new Util\Textarea('body', '', 80, 20),
    'current'      => [
        'aotm'     => $manager->findByType(Enum\FeaturedAlbumType::AlbumOfTheMonth),
        'showcase' => $manager->findByType(Enum\FeaturedAlbumType::Showcase),
    ],
    'feature'      => [
        'aotm'     => Enum\FeaturedAlbumType::AlbumOfTheMonth,
        'showcase' => Enum\FeaturedAlbumType::Showcase,
    ],
    'leech_type'   => Enum\LeechType::cases(),
    'leech_reason' => [
        Enum\LeechReason::AlbumOfTheMonth,
        Enum\LeechReason::Showcase,
    ],
    'size'         => $size,
    'unit'         => $unit,
    'viewer'       => $Viewer,
]);
