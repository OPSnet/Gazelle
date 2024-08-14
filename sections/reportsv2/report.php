<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
/*
 * This is the frontend of reporting a torrent, it's what users see when
 * they visit reportsv2.php?id=xxx
 */

$torMan = (new Gazelle\Manager\Torrent())->setViewer($Viewer);
$torrentId = (int)($_GET['id'] ?? 0);
$torrent   = $torMan->findById($torrentId);
if (is_null($torrent)) {
    // Deleted torrent
    header("Location: log.php?search=Torrent+$torrentId");
    exit;
}

$tgroup        = $torrent->group();
$GroupID       = $tgroup->id();
$CategoryID    = $tgroup->categoryId();
$remasterTuple = false;
$FirstUnknown  = $torrent->isRemasteredUnknown();
$EditionID     = 0;

$reportMan      = new Gazelle\Manager\Torrent\Report(new Gazelle\Manager\Torrent());
$reportTypeMan  = new Gazelle\Manager\Torrent\ReportType();
$reportTypeList = $reportTypeMan->categoryList($CategoryID);
$snatcher       = $Viewer->snatch();
$urlStem        = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$userMan        = new Gazelle\Manager\User();
$reportList     = array_map(fn ($id) => $reportMan->findById($id), $torrent->reportIdList($Viewer));

View::show_header('Report', ['js' => 'reportsv2,browse,torrent,bbcode']);
?>
<div class="thin">
    <div class="header">
        <h2>Report a torrent</h2>
    </div>
    <div class="header">
        <h3><?= $tgroup->link() ?></h3>
    </div>
    <div class="thin">
        <table class="torrent_table details<?= $snatcher->showSnatch($torrent) ? ' snatched' : '' ?>" id="torrent_details">
            <tr class="colhead_dark">
                <td width="80%"><strong>Reported torrent</strong></td>
                <td><strong>Size</strong></td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
            </tr>
<?php
echo $Twig->render('torrent/detail-torrentgroup.twig', [
    'report_man'    => $reportMan,
    'show_extended' => true,
    'show_id'       => $torrentId,
    'snatcher'      => $snatcher,
    'tgroup'        => $tgroup,
    'torrent_list'  => [$torrent],
    'tor_man'       => $torMan,
    'viewer'        => $Viewer,
]); ?>
        </table>
    </div>

    <form class="create_form" name="report" action="reportsv2.php?action=takereport" enctype="multipart/form-data" method="post" id="reportform">
        <div>
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="torrentid" value="<?=$torrentId?>" />
            <input type="hidden" name="categoryid" value="<?= $CategoryID ?>" />
        </div>

        <h3>Report Information</h3>
        <div class="box pad">
            <table class="layout">
                <tr>
                    <td class="label">Reason:</td>
                    <td>
                        <select id="type" name="type">
<?php foreach ($reportTypeList as $rt) { ?>
            <option value="<?= $rt->type() ?>"><?= $rt->name() ?></option>
<?php } ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p>Fields that contain lists of values (for example, listing more than one track number) should be separated by a space.</p>
            <br />
            <p><strong>Following the below report type specific guidelines will help the moderators deal with your report in a timely fashion. </strong></p>
            <br />

            <div id="dynamic_form">
                <input id="sitelink" type="hidden" name="sitelink" size="50" value="<?= display_str($_POST['sitelink'] ?? '') ?>" />
                <input id="image" type="hidden" name="image" size="50" value="<?= display_str($_POST['image'] ?? '') ?>" />
                <input id="track" type="hidden" name="track" size="8" value="<?= display_str($_POST['track'] ?? '') ?>" />
                <input id="link" type="hidden" name="link" size="50" value="<?= display_str($_POST['link'] ?? '') ?>" />
                <input id="extra" type="hidden" name="extra" value="<?= display_str($_POST['extra'] ?? '') ?>" />
            </div>
        </div>
    <input type="submit" value="Create report" />
    </form>
</div>
<?php
View::show_footer();
