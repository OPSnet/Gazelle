<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$LogID = (int)($_POST['logid'] ?? 0);
if (!$LogID) {
    error(404);
}
$torrent = (new Gazelle\Manager\Torrent())->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

$adjusted          = false;
$adjustedChecksum  = isset($_POST['adjusted_checksum']);
$adjustmentReason  = $_POST['adjustment_reason'];
$adjustedScore     = 100;
$adjustmentDetails = [];

if ($adjustedChecksum != $torrent->logChecksum()) {
    $adjusted = true;
    $adjustmentDetails['checksum'] = 'Checksum manually ' . ($adjustedChecksum ? 'validated' : 'invalidated');
}

$Deductions = [
    ['read_mode_secure', 20, 'Non-Secure Mode used'],
    ['audio_cache',      10, 'Defeat/disable audio cache should be yes'],
    ['c2_points',        10, 'C2 Pointers enabled'],
    ['drive_offset',      5, 'Incorrect drive offset'],
    ['fill_offsets',      5, 'Does not fill up missing offset samples with silence'],
    ['deletes_ofsets',    5, 'Deletes leading and trailing silent blocks'],
    ['gap_handling',     10, 'Gap handling should be appended to previous track'],
    ['test_and_copy',    10, 'Test & Copy not used'],
    ['range_rip',        30, 'Range Rip'],
    ['null_samples',      5, 'Null samples should be used in CRC calculations'],
    ['eac_old',          30, 'EAC older than 0.99'],
    ['id3_tags',          1, 'ID3 tags found'],
    ['foreign_log',       0, 'Unrecognized foreign log'],
    ['combined_log',      0, 'Combined log']
];

foreach ($Deductions as [$tag, $deduction, $label]) {
    if (isset($_POST[$tag])) {
        $adjusted = true;
        $adjustedScore -= $deduction;
        if ($deduction > 0) {
            $label .= " (-{$deduction} points)";
        }
        $adjustmentDetails[$tag] = $label;
    }
}

$TrackDeductions = [
    ['crc_mismatches',       30, 'CRC mismatches'],
    ['suspicious_positions', 20, 'Suspicious positions'],
    ['timing_problems',      20, 'Timing problems']
];

foreach ($TrackDeductions as [$tag, $deduction, $label]) {
    $n = (int)($_POST[$tag] ?? 0);
    if ($n > 0) {
        $score = $n * $deduction;
        $adjustmentDetails[$tag] = "$n $label (-{$score} points)";
        $adjustmentDetails['tracks'][$tag] = $n; /** @phpstan-ignore-line */
        $adjustedScore -= $score;
        $adjusted = true;
    }
}

$torrent->adjustLogscore($LogID, $adjusted, max(0, $adjustedScore), $adjustedChecksum, $Viewer->id(), $adjustmentReason, $adjustmentDetails);

header('Location: ' . $torrent->location());
