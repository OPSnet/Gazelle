<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

use Gazelle\Enum\CacheBucket;

header('Access-Control-Allow-Origin: *');

$tgMan  = (new Gazelle\Manager\TGroup())->setViewer($Viewer);
$tgroup = $tgMan->findById((int)($_GET['id'] ?? 0));
if (is_null($tgroup)) {
    error(404);
}
$tgroupId = $tgroup->id();
$RevisionID = (int)($_GET['revisionid'] ?? 0);

// Comments (must be loaded before View::show_header so that subscriptions and quote notifications are handled properly)
$commentPage = new Gazelle\Comment\Torrent($tgroupId, (int)($_GET['page'] ?? 0), (int)($_GET['postid'] ?? 0));
$commentPage->load()->handleSubscription($Viewer);

$paginator = new Gazelle\Util\Paginator(TORRENT_COMMENTS_PER_PAGE, $commentPage->pageNum());
$paginator->setAnchor('comments')->setTotal($commentPage->total())->removeParam('postid');

$artistMan     = new Gazelle\Manager\Artist();
$collageMan    = new Gazelle\Manager\Collage();
$torMan        = (new Gazelle\Manager\Torrent())->setViewer($Viewer);
$reportMan     = new Gazelle\Manager\Torrent\Report($torMan);
$requestMan    = new Gazelle\Manager\Request();
$userMan       = new Gazelle\Manager\User();
$snatcher      = $Viewer->snatch();
$vote          = new Gazelle\User\Vote($Viewer);

$isSubscribed  = (new Gazelle\User\Subscription($Viewer))->isSubscribedComments('torrents', $tgroupId);
$releaseTypes  = (new Gazelle\ReleaseType())->list();
$urlStem       = (new Gazelle\User\Stylesheet($Viewer))->imagePath();

$categoryId    = $tgroup->categoryId();
$musicRelease  = $tgroup->categoryName() == 'Music';
$year          = $tgroup->year();
$torrentList   = $tgroup->torrentIdList();
$removed       = $torrentList ? [] : $tgroup->deletedMasteringList();

$section = [
    ['id' => ARTIST_COMPOSER,  'name' => 'composer',  'class' => 'artists_composers',  'role' => 'Composer',  'title' => 'Composers:'],
    ['id' => ARTIST_DJ,        'name' => 'dj',        'class' => 'artists_dj',         'role' => 'DJ',        'title' => 'DJ / Compiler:'],
    ['id' => ARTIST_MAIN,      'name' => 'main',      'class' => 'artists_main',       'role' => 'Artist',    'title' => empty($role['conductor']) ? 'Artists:' : 'Performers:'],
    ['id' => ARTIST_GUEST,     'name' => 'guest',     'class' => 'artists_guest',      'role' => 'Guest',     'title' => 'With:'],
    ['id' => ARTIST_CONDUCTOR, 'name' => 'conductor', 'class' => 'artists_conductors', 'role' => 'Conductor', 'title' => 'Conducted by:'],
    ['id' => ARTIST_REMIXER,   'name' => 'remixer',   'class' => 'artists_remix',      'role' => 'Remixer',   'title' => 'Remixed by:'],
    ['id' => ARTIST_PRODUCER,  'name' => 'producer',  'class' => 'artists_producer',   'role' => 'Producer',  'title' => 'Produced by:'],
    ['id' => ARTIST_ARRANGER,  'name' => 'arranger',  'class' => 'artists_arranger',   'role' => 'Arranger',  'title' => 'Arranged by:'],
];

echo $Twig->render('torrent/detail-header.twig', [
    'is_bookmarked' => (new Gazelle\User\Bookmark($Viewer))->isTorrentBookmarked($tgroup->id()),
    'is_subscribed' => $isSubscribed,
    'revision_id'   => $RevisionID,
    'tgroup'        => $tgroup,
    'viewer'        => $Viewer,
]);
?>

<?php
if ($musicRelease) {
    $role = $tgroup->artistRole()->roleList();
?>
        <div class="box box_artists">
            <div class="head"><strong>Artists</strong>
<?php if ($Viewer->permitted('torrents_edit')) { ?>
            <span style="float: right;" class="edit_artists"><a onclick="ArtistManager(); return false;" href="#" class="brackets">Edit</a></span>
<?php } ?>
            </div>
            <ul class="stats nobullet" id="artist_list">
<?php
    foreach ($section as $s) {
        if ($role[$s['name']]) {
?>
                <li class="<?= $s['class'] ?>"><strong class="artists_label"><?= $s['title'] ?></strong></li>
<?php
            foreach ($role[$s['name']] as $artistInfo) {
                $artist = $artistMan->findByAliasId($artistInfo['aliasid']);
                if (is_null($artist)) {
                    continue;
                }
?>
                <li class="<?= $s['class'] ?> artist_entry" data-aliasid="<?= $artist->aliasId() ?>">
                    <?= $artist->link() ?>&lrm;
<?php           if ($Viewer->permitted('torrents_edit')) { ?>
                    (<span class="tooltip" title="Artist alias ID"><?= $artist->aliasId()
                        ?></span>)&nbsp;<span class="remove remove_artist"><a href="javascript:void(0);" onclick="ajax.get('torrents.php?action=delete_alias&amp;auth='+authkey+'&amp;groupid=<?=
                        $tgroupId ?>&amp;aliasid=<?= $artist->aliasId() ?>&amp;importance=<?=
                        $s['id'] ?>'); this.parentNode.parentNode.style.display = 'none';" class="brackets tooltip" title="Remove <?=
                        $s['role'] ?>">X</a></span>
<?php           } ?>
                </li>
<?php
            }
        }
    } /* foreach section */
?>
            </ul>
        </div>
<?php
    if ($Viewer->permitted('torrents_add_artist')) {
        usort($section, fn ($x, $y) => $x['id'] <=> $y['id']);
?>
        <div class="box box_addartists">
            <div class="head"><strong>Add artist</strong><span style="float: right;" class="additional_add_artist"><a onclick="AddArtistField(); return false;" href="#" class="brackets">+</a></span></div>
            <div class="body">
                <form class="add_form" name="artists" action="torrents.php" method="post">
                    <div id="AddArtists">
                        <input type="hidden" name="action" value="add_alias" />
                        <input type="hidden" name="auth" value="<?=$Viewer->auth() ?>" />
                        <input type="hidden" name="groupid" value="<?=$tgroupId?>" />
                        <input type="text" id="artist" name="aliasname[]" size="17"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                        <select name="importance[]">
<?php       foreach ($section as $s) { ?>
                            <option value="<?= $s['id'] ?>"><?= $s['role'] === 'Artist' ? 'Main' : $s['role'] ?></option>
<?php       } ?>
                        </select>
                    </div>
                    <input type="submit" value="Add" />
                </form>
            </div>
        </div>
<?php
    }
}

echo $Twig->render('tgroup/stats.twig', [
    'collage_list' => $collageMan->addToCollageDefault($tgroupId, $Viewer),
    'featured'     => (new Gazelle\Manager\FeaturedAlbum())->findById($tgroupId),
    'tag_undo'     => $Cache->get_value("deleted_tags_{$tgroupId}_{$Viewer->id()}"),
    'tgroup'       => $tgroup,
    'viewer'       => $Viewer,
    'vote'         => $vote,
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
                <td><strong>Size</strong></td>
                <td class="m_th_right sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="m_th_right sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="m_th_right sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
if (!$torrentList) {
    // if there are no live torrents left in this group, retrieve info about deleted masterings
    foreach ($removed as $info) {
        $mastering = implode('/', [$info['year'], $info['title'], $info['record_label'], $info['catalogue_number'], $info['media']]);
?>
            <tr class="releases_<?= $tgroup->releaseType() ?> groupid_<?=$tgroupId?> edition group_torrent">
                <td colspan="5" class="edition_info"><strong>[<?= html_escape($mastering) ?>]</strong></td>
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
            'report_man'      => $reportMan,
            'show_extended'   => true,
            'show_id'         => ($_GET['torrentid'] ?? ''),
            'snatcher'        => $snatcher,
            'tgroup'          => $tgroup,
            'torrent_list'    => object_generator($torMan, $torrentList),
            'tor_man'         => $torMan,
            'viewer'          => $Viewer,
        ]);
?>
        </table>
<?php
}

if (!$Viewer->disableRequests()) {
    echo $Twig->render('request/torrent.twig', [
        'bounty' => $Viewer->ordinal()->value('request-bounty-vote'),
        'list'   => $requestMan->findByTGroup($tgroup),
        'viewer' => $Viewer,
    ]);
}

echo $Twig->render('tgroup/similar.twig', [
    'similar' => $tgMan->similarVote($tgroup),
]);
?>
        <div class="box torrent_description">
            <div class="head"><a href="#">&uarr;</a>&nbsp;<strong><?= $tgroup->releaseTypeName() ? $tgroup->releaseTypeName() . ' info' : 'Info' ?></strong></div>
            <div class="body">
<?php if (!empty($tgroup->description())) { ?>
                <?= Text::full_format($tgroup->description(), cache: IMAGE_CACHE_ENABLED, bucket: CacheBucket::tgroup) ?>
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
    'textarea'  => (new Gazelle\Util\Textarea('quickpost', ''))->setPreviewManual(true),
    'url'       => $_SERVER['REQUEST_URI'],
    'url_stem'  => 'comments.php?page=torrents',
    'userMan'   => $userMan,
    'viewer'    => $Viewer,
]) ?>
    </div>
</div>
<?php
View::show_footer();
