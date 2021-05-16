<?php

if (!check_perms('users_mod')) {
    error(403);
}

$LogID = (int)($_POST['logid'] ?? 0);
if (!$LogID) {
    error(404);
}
$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

$Adjusted = isset($_POST['adjusted']) ? '1' : '0';
$AdjustedScore = 100;
$AdjustedChecksum = isset($_POST['adjusted_checksum']) ? '1' : '0';
$AdjustmentReason = $_POST['adjustment_reason'];
$AdjustmentDetails = [];

if ($AdjustedChecksum != $torrent->info()['Checksum']) {
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

$torrent->adjustLogscore($LogID, $Adjusted, max(0, $AdjustedScore), $AdjustedChecksum, $LoggedUser['ID'], $AdjustmentReason, $AdjustmentDetails);

header("Location: torrents.php?torrentid=" . $torrent->id());
