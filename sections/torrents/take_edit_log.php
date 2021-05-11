<?php

if (!check_perms('users_mod')) {
    error(403);
}

$TorrentID = intval($_POST['torrentid']);
$LogID = intval($_POST['logid']);

[$GroupID, $Checksum] = $DB->row("
    SELECT t.GroupID, tl.Checksum
    FROM torrents_logs tl
    INNER JOIN torrents t ON (tl.TorrentID = t.ID)
    WHERE tl.TorrentID = ? AND tl.LogID = ?
    ", $TorrentID, $LogID
);
if (!$GroupID) {
    error(404);
}

$Adjusted = isset($_POST['adjusted']) ? '1' : '0';
$AdjustedScore = 100;
$AdjustedChecksum = isset($_POST['adjusted_checksum']) ? '1' : '0';
$AdjustedBy = $LoggedUser['ID'];
$AdjustmentReason = $_POST['adjustment_reason'];
$AdjustmentDetails = [];

if ($AdjustedChecksum != $Checksum) {
    $AdjustmentDetails['checksum'] = 'Checksum manually '.($AdjustedChecksum == '1' ? 'validated' : 'invalidated');
}

$Deductions = [
    ['read_mode_secure', 'Non-Secure Mode used', 20],
    ['audio_cache', 'Defeat/disable audio cache should be yes', 10],
    ['c2_points', 'C2 Pointers enabled', 10],
    ['drive_offset', 'Incorred drive offset', 5],
    ['fill_offsets', 'Does not fill up missing offset samples with silence', 5],
    ['deletes_ofsets', 'Deletes leading and trailing silent blocks', 5],
    ['gap_handling', 'Gap handling should be appended to previous track', 10],
    ['test_and_copy', 'Test & Copy not used', 10],
    ['range_rip', 'Range Rip', 30],
    ['null_samples', 'Null samples should be used in CRC calculations', 5],
    ['eac_old', 'EAC older than 0.99', 30],
    ['id3_tags', 'ID3 tags found', 1],
    ['foreign_log', 'Unrecognized foreign log'],
    ['combined_log', 'Combined log']
];

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

$TrackDeductions = [
    ['crc_mismatches', 'CRC mismatches', 30],
    ['suspicious_positions', 'Suspicious positions', 20],
    ['timing_problems', 'Timing problems', 20]
];

foreach ($TrackDeductions as $Deduction) {
    $Count = intval($_POST[$Deduction[0]]);
    $Total = $Deduction[2] * $Count;
    if ($Count > 0) {
        $AdjustmentDetails[$Deduction[0]] = "{$Count} {$Deduction[1]} (-{$Total} points)";
    }
    $AdjustmentDetails['tracks'][$Deduction[0]] = $Count;
    $AdjustedScore -= $Total;
}

(new Gazelle\Manager\Torrent)->adjustLogscore($GroupID, $TorrentID, $LogID, $Adjusted, max(0, $AdjustedScore), $AdjustedChecksum, $AdjustedBy, $AdjustmentReason, serialize($AdjustmentDetails));

header("Location: torrents.php?torrentid={$TorrentID}");
