<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\CacheBucket;

header('Access-Control-Allow-Origin: *');

$tgMan  = new Manager\TGroup();
$tgroup = $tgMan->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    Error404::error();
}

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$commentPage = new Comment\Torrent($tgroup->id, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$collageMan   = new Manager\Collage();
$torMan       = new Manager\Torrent();
$isSubscribed = new User\Subscription($Viewer)->isSubscribedComments('torrents', $tgroup->id);
$urlStem      = new User\Stylesheet($Viewer)->imagePath();
$torrentList  = $tgroup->torrentIdList();
$roleList     = $tgroup->artistRole()?->roleList();

$section = [
    ['id' => ARTIST_COMPOSER,  'name' => 'composer',  'class' => 'artists_composers',  'role' => 'Composer',      'title' => 'Composers:'],
    ['id' => ARTIST_DJ,        'name' => 'dj',        'class' => 'artists_dj',         'role' => 'DJ / Compiler', 'title' => 'DJ / Compiler:'],
    ['id' => ARTIST_MAIN,      'name' => 'main',      'class' => 'artists_main',       'role' => 'Artist',        'title' => isset($roleList['conductor']) ? 'Performers:' : 'Artists:'],
    ['id' => ARTIST_GUEST,     'name' => 'guest',     'class' => 'artists_guest',      'role' => 'Guest',         'title' => 'With:'],
    ['id' => ARTIST_CONDUCTOR, 'name' => 'conductor', 'class' => 'artists_conductors', 'role' => 'Conductor',     'title' => 'Conducted by:'],
    ['id' => ARTIST_REMIXER,   'name' => 'remixer',   'class' => 'artists_remix',      'role' => 'Remixer',       'title' => 'Remixed by:'],
    ['id' => ARTIST_PRODUCER,  'name' => 'producer',  'class' => 'artists_producer',   'role' => 'Producer',      'title' => 'Produced by:'],
    ['id' => ARTIST_ARRANGER,  'name' => 'arranger',  'class' => 'artists_arranger',   'role' => 'Arranger',      'title' => 'Arranged by:'],
];

echo $Twig->render('torrent/detail-header.twig', [
    'is_bookmarked' => new User\Bookmark($Viewer)->isBookmarked($tgroup),
    'is_subscribed' => $isSubscribed,
    'revision_id'   => (int)($_GET['revisionid'] ?? 0),
    'tgroup'        => $tgroup,
    'viewer'        => $Viewer,
]);

if ($tgroup->categoryName() == 'Music') {
    echo $Twig->render('tgroup/artist-sidebar.twig', [
        'role'    => $roleList,
        'section' => $section,
        'tgroup'  => $tgroup,
        'viewer'  => $Viewer,
    ]);
}

echo $Twig->render('tgroup/stats.twig', [
    'collage_list' => $collageMan->addToCollageDefault($tgroup, $Viewer),
    'featured'     => new Manager\FeaturedAlbum()->findById($tgroup->id),
    'tag_undo'     => $Cache->get_value("deleted_tags_{$tgroup->id}_{$Viewer->id}"),
    'tgroup'       => $tgroup,
    'viewer'       => $Viewer,
    'vote'         => new User\Vote($Viewer),
]);
?>
    </div>
    <div class="main_column">
<?php
echo $Twig->render('collage/summary.twig', [
    'class'   => 'collage_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupGeneralSummary($tgroup),
]);

echo $Twig->render('collage/summary.twig', [
    'class'   => 'personal_rows',
    'object'  => 'album',
    'summary' => $collageMan->tgroupPersonalSummary($tgroup),
]);
?>
        <table class="torrent_table details<?= $tgroup->isSnatched() ? ' snatched' : ''?> m_table" id="torrent_details">
            <tr class="colhead_dark">
                <td class="m_th_left" width="80%"><strong>Torrents</strong></td>
<?php if ($Viewer->ordinal()->value('file-count-display')) { ?>
                <td class="number_column"><strong>Files</strong></td>
<?php } ?>
                <td class="number_column"><strong>Size</strong></td>
                <td class="m_th_right sign snatches"><img src="<?= $urlStem ?>snatched.svg" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="m_th_right sign seeders"><img src="<?= $urlStem ?>seeders.svg" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="m_th_right sign leechers"><img src="<?= $urlStem ?>leechers.svg" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
if (!$torrentList) {
    // if there are no live torrents left in this group, retrieve info about deleted masterings
    foreach ($tgroup->deletedMasteringList() as $info) {
        $mastering = implode('/', [$info['year'], $info['title'], $info['record_label'], $info['catalogue_number'], $info['media']]);
?>
            <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?= $tgroup->id ?> edition group_torrent">
                <td colspan="<?= $Viewer->ordinal()->value('file-count-display') ? 6 : 5 ?>" class="edition_info"><strong>[<?= html_escape($mastering) ?>]</strong></td>
            </tr>
            <tr>
                <td><i>deleted</i></td>
                <td class="td_size nobr">–</td>
                <td class="td_snatched m_td_right">–</td>
                <td class="td_seeders m_td_right">–</td>
                <td class="td_leechers m_td_right">–</td>
            </tr>
<?php
    }
} else {
        echo $Twig->render('torrent/detail-torrentgroup.twig', [
            'is_snatched_grp' => $tgroup->isSnatched(),
            'report_man'      => new Manager\Torrent\Report($torMan),
            'show_extended'   => true,
            'show_id'         => ($_GET['torrentid'] ?? ''),
            'snatcher'        => $Viewer->snatch(),
            'tgroup'          => $tgroup,
            'torrent_list'    => object_generator($torMan, $torrentList),
            'tor_man'         => $torMan,
            'viewer'          => $Viewer,
        ]);
} ?>
        </table>
<?php
if (!$Viewer->disableRequests()) {
    echo $Twig->render('request/torrent.twig', [
        'bounty' => $Viewer->ordinal()->value('request-bounty-vote'),
        'list'   => new Manager\Request()->findByTGroup($tgroup),
        'viewer' => $Viewer,
    ]);
}

echo $Twig->render('tgroup/similar.twig', [
    'similar' => $tgMan->similarVote($tgroup),
]);
?>
        <div class="box torrent_description">
            <div class="head"><a href="#">↑</a>&nbsp;<strong><?= $tgroup->releaseTypeName() ? $tgroup->releaseTypeName() . ' info' : 'Info' ?></strong></div>
            <div class="body">
<?php if (!empty($tgroup->description())) { ?>
                <?= \Text::full_format($tgroup->description(), cache: Enum\ImageCacheMode::cache, bucket: CacheBucket::tgroup) ?>
<?php } else { ?>
                There is no information on this torrent.
<?php } ?>
            </div>
        </div>

<?= $Twig->render('comment/thread.twig', [
    'object'    => $tgroup,
    'comment'   => $commentPage,
    'paginator' => $paginator,
    'subbed'    => $isSubscribed,
    'textarea'  => new Util\Textarea('quickpost', '')->setPreviewManual(true),
    'url'       => $_SERVER['REQUEST_URI'],
    'url_stem'  => 'comments.php?page=torrents',
    'userMan'   => new Manager\User(),
    'viewer'    => $Viewer,
]) ?>
    </div>
</div>
<?php
View::show_footer();
