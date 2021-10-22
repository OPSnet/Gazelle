<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$TorrentID = intval($_GET['torrentid']);
$LogID = intval($_GET['logid']);

$GroupID = $DB->scalar('SELECT GroupID FROM torrents WHERE ID = ?', $TorrentID);
if (!$GroupID) {
    error(404);
}
$Group = Torrents::array_group(Torrents::get_groups([$GroupID])[$GroupID]);
$TorrentTags = new Tags($Group['TagList']);

if (!empty($Group['ExtendedArtists'][1]) || !empty($Group['ExtendedArtists'][4]) || !empty($Group['ExtendedArtists'][5])) {
    unset($Group['ExtendedArtists'][2]);
    unset($Group['ExtendedArtists'][3]);
    $DisplayName = Artists::display_artists($Group['ExtendedArtists']);
} elseif (!empty($Artists)) {
    $DisplayName = Artists::display_artists([1 => $Group['Artists']]);
} else {
    $DisplayName = '';
}
$DisplayName .= '<a href="torrents.php?id=' . $GroupID . '&amp;torrentid=' . $TorrentID . '" class="tooltip" title="View torrent" dir="ltr">' . $Group['GroupName'] . '</a>';
if ($Group['GroupYear'] > 0) {
    $DisplayName .= " [{$Group['GroupYear']}";
}
if ($Group['GroupVanityHouse']) {
    $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
}

$ExtraInfo = Torrents::torrent_info($Group['Torrents'][$TorrentID]);
if ($ExtraInfo) {
    $DisplayName .= " - $ExtraInfo";
}

$DB->prepared_query('
    SELECT FileName, Details, Score, Checksum, Adjusted, AdjustedScore, AdjustedChecksum, AdjustedBy, AdjustmentReason, AdjustmentDetails
    FROM torrents_logs
    WHERE TorrentID = ? AND LogID = ?
    ', $TorrentID, $LogID
);
if (!$DB->has_results()) {
    error(404);
}

$Log = $DB->next_record(MYSQLI_ASSOC, ['AdjustmentDetails']);
$Checksum = ($Log['Checksum'] == '1') ? 'Good' : 'Missing/Invalid Checksum';
$Details = "";
if (!empty($Log['Details'])) {
    $Log['Details'] = explode("\r\n", $Log['Details']);
    $Details .= '<ul>';
    foreach($Log['Details'] as $Entry) {
        $Details .='<li>'.$Entry.'</li>';
    }
    $Details .= '</ul>';
}

$AdjustedScore = (!isset($Log['AdjustedScore']) || $Log['Adjusted'] == '0') ? $Log['Score'] : $Log['AdjustedScore'];
$AdjustedUser = (!empty($Log['AdjustedBy'])) ? "(By: ".Users::format_username($Log['AdjustedBy']).")" : "";
$AdjustedChecksum = ($Log['Adjusted'] == '0') ? $Log['Checksum'] : $Log['AdjustedChecksum'];
$AdjustmentDetails = ['tracks' => ['crc_mismatches' => 0, 'suspicious_positions' => 0, 'timing_problems' => 0]];
if (!empty($Log['AdjustmentDetails'])) {
    $AdjustmentDetails = unserialize($Log['AdjustmentDetails']);
}
View::show_header("Edit Log", ['js' => 'edit_log']);
?>
<div class="thin">
    <h2 class="center">Edit Log</h2>
    <form action="torrents.php?action=take_editlog" method="post" name="edit_log">
        <input type="hidden" name="logid" value="<?=$LogID?>" />
        <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        <table class="layout border">
            <tr class="colhead">
                <td colspan="3">Log Details</td>
            </tr>
            <tr>
                <td>Torrent</td>
                <td colspan="2"><?=$DisplayName?></td>
            </tr>
            <tr>
                <td>Log File</td>
                <td colspan="2"><?=$Log['FileName']?> (<a href="view.php?type=riplog&id=<?= $TorrentID ?>.<?= $LogID ?>" target="_blank">View Raw</a>)</td>
            </tr>
            <tr>
                <td>Score</td>
                <td colspan="2"><?=$Log['Score']?> (<a href="torrents.php?action=rescore_log&logid=<?=$LogID?>&torrentid=<?=$TorrentID?>">Rescore Log</a>)</td>
            </tr>
            <tr>
                <td>Checksum</td>
                <td colspan="2"><?=$Checksum?></td>
            </tr>
            <tr>
                <td>Log Validation Report</td>
                <td colspan="2"><?=$Details?></td>
            </tr>
            <tr class="colhead">
                <td colspan="3">Manual Adjustment</td>
            </tr>
            <tr>
                <td>Manually Adjusted</td>
                <td colspan="2"><input type="checkbox" name="adjusted" <?=($Log['Adjusted'] == '1' ? 'checked' : '')?> /> <?=$AdjustedUser?></td>
            </tr>
            <tr>
                <td>Adjusted Score</td>
                <td colspan="2"><input type="text" name="adjusted_score" value="<?=$AdjustedScore?>" disabled="disabled" data-actual="100"/></td>
            </tr>
            <tr>
                <td>Checksum Valid</td>
                <td colspan="2"><input type="checkbox" name="adjusted_checksum" <?=($AdjustedChecksum == '1' ? 'checked' : '')?>/></td>
            </tr>
            <tr>
                <td>Adjustment Reason</td>
                <td colspan="2"><input type="text" name="adjustment_reason" value="<?=$Log['AdjustmentReason']?>" size="100" /></td>
            </tr>
            <tr>
                <td rowspan="4">Audio Deductions</td>
                <td><label><input type="checkbox" name="read_mode_secure" <?=isset_array_checked($AdjustmentDetails, 'read_mode_secure')?> data-score="20"/> Non-Secure Mode used (-20 points)</label></td>
                <td><label><input type="checkbox" name="audio_cache" <?=isset_array_checked($AdjustmentDetails, 'audio_cache')?> data-score="10" /> Defeat/disable audio cache should be yes (-10 points)</label></td>
            </tr>
            <tr>
                <td style="display: none"></td>
                <td><label><input type="checkbox" name="c2_points" <?=isset_array_checked($AdjustmentDetails, 'c2_points')?> data-score="10" /> C2 Pointers enabled (-10 points)</td>
                <td><label><input type="checkbox" name="drive_offset" <?=isset_array_checked($AdjustmentDetails, 'drive_offset')?> data-score="5" /> Incorred drive offset (-5 points)</td>
            </tr>
            <tr>
                <td style="display: none"></td>
                <td><label><input type="checkbox" name="fill_offsets" <?=isset_array_checked($AdjustmentDetails, 'fill_offsets')?> data-score="5" /> Does not fill up missing offset samples with silence (-5 points)</td>
                <td><label><input type="checkbox" name="deletes_ofsets" <?=isset_array_checked($AdjustmentDetails, 'deletes_ofsets')?> data-score="5" /> Deletes leading and trailing silent blocks (-5 points)</td>
            </tr>
            <tr>
                <td style="display: none"></td>
                <td><label><input type="checkbox" name="gap_handling" <?=isset_array_checked($AdjustmentDetails, 'gap_handling')?> data-score="10" /> Gap handling should be appended to previous track (-10 points)</td>
                <td><label><input type="checkbox" name="test_and_copy" <?=isset_array_checked($AdjustmentDetails, 'test_and_copy')?> data-score="10" /> Test & Copy not used (-10 points)</td>
            </tr>
            <tr>
                <td rowspan="3">Track Deductions</td>
                <td>CRC Mismatches (-30 each)</td>
                <td><input type="text" name="crc_mismatches" value="<?=$AdjustmentDetails['tracks']['crc_mismatches']?>" data-score="30"/></td>
            </tr>
            <tr>
                <td style="display:none"></td>
                <td>Suspicious Positions (-20 each)></td>
                <td><input type="text" name="suspicious_positions" value="<?=$AdjustmentDetails['tracks']['suspicious_positions']?>" data-score="20"/></td>
            </tr>
            <tr>
                <td style="display:none"></td>
                <td>Timing Problems (-20 each)</td>
                <td><input type="text" name="timing_problems" value="<?=$AdjustmentDetails['tracks']['timing_problems']?>" data-score="20"/></td>
            </tr>
            <tr>
                <td rowspan="2">Non-Audio Deductions</td>
                <td><label><input type="checkbox" name="range_rip" <?=isset_array_checked($AdjustmentDetails, 'range_rip')?> data-score="30" /> Range Rip (-30 points)</td>
                <td><label><input type="checkbox" name="null_samples" <?=isset_array_checked($AdjustmentDetails, 'null_samples')?> data-score="5" /> Null samples should be used in CRC calculations (-5 points)</td>
            </tr>
            <tr>
                <td style="display:none"></td>
                <td><label><input type="checkbox" name="eac_old" <?=isset_array_checked($AdjustmentDetails, 'eac_old')?> data-score="30" /> EAC older than 0.99 (-30 points)</td>
                <td><label><input type="checkbox" name="id3_tags" <?=isset_array_checked($AdjustmentDetails, 'id3_tags')?> data-score="1" /> ID3 tags found (-1 points)</td>
            </tr>
            <tr>
                <td rowspan="1">Other Reaons</td>
                <td><label><input type="checkbox" name="foreign_log" <?=isset_array_checked($AdjustmentDetails, 'foreign_log')?> /> Foreign Log</label></td>
                <td><label><input type="checkbox" name="combined_log" <?=isset_array_checked($AdjustmentDetails, 'combined_log')?> /> Combined Log</label></td>
            </tr>
            <tr style="text-align: center">
                <td colspan="3"><input type="submit" value="Rescore Log" /></td>
            </tr>
        </table>
    </form>
</div>

<?php
View::show_footer();
