<?php

if (!check_perms('users_mod')) {
    error(403);
}

$TorrentID = intval($_POST['torrentid']);
$LogID = intval($_POST['logid']);

$DB->query("SELECT GroupID FROM torrents WHERE ID='{$TorrentID}'");
if (!$DB->has_results()) {
    error(404);
}
list($GroupID) = $DB->next_record(MYSQLI_NUM);

$DB->query("SELECT Log, FileName, Details, Score, Checksum, Adjusted, AdjustedScore, AdjustedChecksum, AdjustedBy, AdjustmentReason, AdjustmentDetails FROM torrents_logs WHERE TorrentID='{$TorrentID}' AND LogID='{$LogID}'");
if (!$DB->has_results()) {
    error(404);
}
$Log = $DB->next_record(MYSQLI_ASSOC);

$Adjusted = isset($_POST['adjusted']) ? '1' : '0';
$AdjustedScore = 100;
$AdjustedChecksum = isset($_POST['adjusted_checksum']) ? '1' : '0';
$AdjustedBy = G::$LoggedUser['ID'];
$AdjustmentReason = $_POST['adjustment_reason'];
$AdjustmentDetails = array();

if ($AdjustedChecksum != $Log['Checksum']) {
    $AdjustmentDetails['checksum'] = 'Checksum manually '.($AdjustedChecksum == '1' ? 'validated' : 'invalidated');
}

$Deductions = array(
    array('read_mode_secure', 'Non-Secure Mode used', 20),
    array('audio_cache', 'Defeat/disable audio cache should be yes', 10),
    array('c2_points', 'C2 Pointers enabled', 10),
    array('drive_offset', 'Incorred drive offset', 5),
    array('fill_offsets', 'Does not fill up missing offset samples with silence', 5),
    array('deletes_ofsets', 'Deletes leading and trailing silent blocks', 5),
    array('gap_handling', 'Gap handling should be appended to previous track', 10),
    array('test_and_copy', 'Test & Copy not used', 10),
    array('range_rip', 'Range Rip', 30),
    array('null_samples', 'Null samples should be used in CRC calculations', 5),
    array('eac_old', 'EAC older than 0.99', 30),
    array('id3_tags', 'ID3 tags found', 1),
    array('foreign_log', 'Unrecognized foreign log'),
    array('combined_log', 'Combined log')
);

foreach ($Deductions as $Deduction) {
    if (isset($_POST[$Deduction[0]])) {
        $Text = $Deduction[1];
        if (isset($Deduction[2]) && $Deduction[2] > 0) {
            $Text .= " (-{$Deduction[2]} points)";
        }
        $AdjustmentDetails[$Deduction[0]] = $Text;
        $AdjustedScore -= $Deduction[2];
    }
}

$TrackDeductions = array(
    array('crc_mismatches', 'CRC mismatches', 30),
    array('suspicious_positions', 'Suspicious positions', 20),
    array('timing_problems', 'Timing problems', 20)
);

foreach ($TrackDeductions as $Deduction) {
    $Count = intval($_POST[$Deduction[0]]);
    $Total = $Deduction[2] * $Count;
    if ($Count > 0) {
        $AdjustmentDetails[$Deduction[0]] = "{$Count} {$Deduction[1]} (-{$Total} points)";
    }
    $AdjustmentDetails['tracks'][$Deduction[0]] = $Count;
    $AdjustedScore -= $Total;
}

$AdjustedScore = max(0, $AdjustedScore);
$AdjustmentDetails = serialize($AdjustmentDetails);

$DB->query("UPDATE torrents_logs SET Adjusted='{$Adjusted}', AdjustedScore='{$AdjustedScore}', AdjustedChecksum='{$AdjustedChecksum}', AdjustedBy='{$AdjustedBy}', AdjustmentReason='".db_string($AdjustmentReason)."', AdjustmentDetails='".db_string($AdjustmentDetails)."' WHERE LogID='{$LogID}' AND TorrentID='{$TorrentID}'");
$DB->query("
UPDATE torrents AS t
JOIN (
	SELECT
		TorrentID,
		MIN(CASE WHEN Adjusted = '1' THEN AdjustedScore ELSE Score END) AS Score,
		MIN(CASE WHEN Adjusted = '1' THEN AdjustedChecksum ELSE Checksum END) AS Checksum
	FROM torrents_logs
	GROUP BY TorrentID
 ) AS tl ON t.ID = tl.TorrentID
SET t.LogScore = tl.Score, t.LogChecksum=tl.Checksum
WHERE t.ID = {$TorrentID}");

$Cache->delete_value("torrent_group_{$GroupID}");
$Cache->delete_value("torrents_details_{$GroupID}");

header("Location: torrents.php?torrentid={$TorrentID}");
