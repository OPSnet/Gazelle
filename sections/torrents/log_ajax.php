<?php
enforce_login();
$TorrentID = (int) $_GET['torrentid'];
if (!isset($TorrentID) || empty($TorrentID)) {
    error(403);
}
$LogScore = isset($_GET['logscore']) ? intval($_GET['logscore']) : 0;
$DB->prepared_query('
    SELECT LogID, Details, Score, `Checksum`, Adjusted, AdjustedBy, AdjustedScore, AdjustedChecksum, AdjustmentReason, AdjustmentDetails, Log
    FROM torrents_logs
    WHERE TorrentID = ?
    ', $TorrentID
);
$ripFiler = new \Gazelle\File\RipLog;

if(!$DB->record_count()) {
    echo '';
} else {
    ob_start();
    echo '<table><tr class=\'colhead_dark\' style=\'font-weight: bold;\'><td>This torrent has '.$DB->record_count().' '.($DB->record_count() > 1 ? 'logs' : 'log').' with a total score of '.$LogScore.' (out of 100):</td></tr>';

    if (check_perms('torrents_delete')) {
        echo "<tr class=\'colhead_dark\' style=\'font-weight: bold;\'><td style='text-align:right;'>
            <a onclick=\"return confirm('This is permanent and irreversible. Missing logs can still be uploaded.');\" href='torrents.php?action=removelogs&amp;torrentid=".$TorrentID."'>Remove all logs</a>
        </td></tr>";
    }

    while ($Log = $DB->next_record(MYSQLI_ASSOC, ['AdjustmentDetails'])) {
        echo "<tr class='log_section'><td>";
        if (check_perms('users_mod')) {
            echo "<a class='brackets' href='torrents.php?action=editlog&torrentid={$TorrentID}&logid={$Log['LogID']}'>Edit Log</a>&nbsp;";
            echo "<a class='brackets' onclick=\"return confirm('Are you sure you want to deleted this log? There is NO undo!');\" href='torrents.php?action=deletelog&torrentid={$TorrentID}&logid={$Log['LogID']}'>Delete Log</a>&nbsp;";
        }
        if ($ripFiler->exists([$TorrentID, $Log['LogID']])) {
            echo "<a class='brackets' href='view.php?type=riplog&id={$TorrentID}.{$Log['LogID']}' target='_blank'>View Raw Log</a>";
        }

        if (($Log['Adjusted'] === '0' && $Log['Checksum'] === '0') || ($Log['Adjusted'] === '1' && $Log['AdjustedChecksum'] === '0')) {
            echo <<<HTML
    <blockquote>
        <strong>Trumpable For:</strong>
        <br /><br />
        Bad/No Checksum(s)
    </blockquote>
HTML;
        }

        if ($Log['Adjusted'] === '1') {
            echo '<blockquote>Log adjusted by '.Users::format_username($Log['AdjustedBy'])." from score {$Log['Score']} to {$Log['AdjustedScore']}";
            if (!empty($Log['AdjustmentReason'])) {
                echo "<br />Reason: {$Log['AdjustmentReason']}";
            }
            $AdjustmentDetails = unserialize($Log['AdjustmentDetails']);
            unset($AdjustmentDetails['tracks']);
            if (!empty($AdjustmentDetails)) {
                echo '<br /><strong>Adjustment Details:</strong><ul>';
                foreach ($AdjustmentDetails as $Entry) {
                    echo '<li>'.$Entry.'</li>';
                }
                echo '</ul>';
            }
            echo '</blockquote>';
        }

        $Log['Details'] = (!empty($Log['Details'])) ? explode("\r\n", trim($Log['Details'])) : [];
        if ($Log['Adjusted'] === '1' && $Log['Checksum'] !== $Log['AdjustedChecksum']) {
            $Log['Details'][] = 'Bad/No Checksum(s)';
        }
        if (!empty($Log['Details'])) {
            $Extra = ($Log['Adjusted'] === '1') ? 'Original ' : '';
            echo '<blockquote><strong>'.$Extra.'Log validation report:</strong><ul>';
            foreach($Log['Details'] as $Entry) {
                echo '<li>'.$Entry.'</li>';
            }
            echo '</ul></blockquote>';
        }

        echo "<blockquote><pre style='white-space:pre-wrap;'>".html_entity_decode($Log['Log'])."</pre></blockquote>";
        echo '</td></tr>';
    }
    echo '</table>';
    echo ob_get_clean();
}
