<?php
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

/*
 * The backend to changing the report type when making a report.
 * It prints out the relevant report_messages from the array, then
 * prints the relevant report fields and whether they're required.
 */

authorize();

$reportType = (new Gazelle\Manager\Torrent\ReportType())->findByType($_POST['type'] ?? '');
if (is_null($reportType)) {
    json_error("bad parameters");
}
?>
<ul>
    <li><?= Text::full_format($reportType->explanation()) ?></li>
</ul>
<br />
<table class="layout border" cellpadding="3" cellspacing="1" border="0" width="100%">
<?php
$needImage = $reportType->needImage();
if ($needImage !== 'none') {
?>
    <tr>
        <td class="label">
            Link(s) to <?= $needImage == 'proof' ? 'proof ' : '' ?>images<?=
    match ($needImage) {
        'proof',
        'required' => ' <strong class="important_text">(Required)</strong>',
        'optional' => ' (Optional)',
        default    => '',
    } ?>:
        </td>
        <td>
            <textarea id="image" name="image" rows="5" cols="60"><?= display_str($_POST['image'] ?? '') ?></textarea>
        </td>
    </tr>
<?php
}

$needTrack = $reportType->needTrack();
if ($needTrack !== 'none') {
?>
    <tr>
        <td class="label">
            Track Number(s)<?= in_array($needTrack, ['all', 'required']) ? ' <strong class="important_text">(Required)</strong>:' : '' ?>
        </td>
        <td>
            <input id="track" type="text" name="track" size="8" value="<?= display_str($_POST['track'] ?? '') ?>" />
            <?= $needTrack === 'all' ? '<input id="all_tracks" type="checkbox" onclick="AllTracks()" /> All' : ''?>
        </td>
    </tr>
<?php
}

$needLink = $reportType->needLink();
if ($needLink !== 'none') {
?>
    <tr>
        <td class="label">
            Link(s) to external source<?=
    match ($needLink) {
        'required' => ' <strong class="important_text">(Required)</strong>',
        'optional' => ' (Optional)',
        default    => '',
    } ?>:
        </td>
        <td>
            <input id="link" type="text" name="link" size="50" value="<?= display_str($_POST['link'] ?? '') ?>" />
        </td>
    </tr>
<?php
}

$needSitelink = $reportType->needSitelink();
if ($needSitelink !== 'none') {
?>
    <tr>
        <td class="label">
            Permalink to <strong>other relevant</strong> torrent(s)<?=
    match ($needSitelink) {
        'required' => ' <strong class="important_text">(Required)</strong>',
        'optional' => ' (Optional)',
        default    => '',
    } ?>:
        </td>
        <td>
            <input id="sitelink" type="text" name="sitelink" size="50" value="<?= display_str($_POST['sitelink'] ?? '') ?>" />
        </td>
    </tr>
<?php } ?>
    <tr>
        <td class="label">
            Comments <strong class="important_text">(Required)</strong>:
        </td>
        <td>
            <textarea id="extra" rows="5" cols="60" name="extra"><?= display_str($_POST['extra'] ?? '') ?></textarea>
        </td>
    </tr>
</table>
