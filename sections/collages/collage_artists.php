<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

/** @var Collage $collage required from collage.php */

echo $Twig->render('collage/header.twig', [
    'bookmarked' => new User\Bookmark($Viewer)->isBookmarked($collage),
    'collage'    => $collage,
    'viewer'     => $Viewer,
]);

echo $Twig->render('collage/sidebar.twig', [
    'artists'      => 0, // only makes sense for torrent collages
    'collage'      => $collage,
    'comments'     => new Manager\Comment()->collageSummary($collage),
    'contributors' => array_slice($collage->contributors(), 0, 5, true),
    'viewer'       => $Viewer,
]);

echo $Twig->render('collage/artist.twig', [
    'collage'    => $collage,
    'image_path' => new User\Stylesheet($Viewer)->imagePath(),
]);
