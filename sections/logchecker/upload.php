<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker', 'upload');

echo <<<HTML
<div class="linkbox">
    <a href="logchecker.php?action=test" class="brackets">Test Logchecker</a>
    <a href="logchecker.php?action=update" class="brackets">Update Logs</a>
</div>
<div class="thin">
    <h2 class="center">Upload Missing Logs</h2>
    <div class="box pad">
        <p>
        These torrents are your uploads that state that there are logs within the torrent, but none were
        uploaded to the site. To fix this, please select a torrent and then some torrents to upload below.
        <br /><br />
        If you'd like to upload new logs for your uploaded torrents that have been scored, please go <a href="logchecker.php?action=update">here</a>.
        Additionally, you can report any torrent to staff for them to be manually rescored by staff.
        </p>
        <br />
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="take_upload" />
            <input type="hidden" name="from_action" value="upload" />
            <table class="form_post vertical_margin">
                <tr class="colhead">
                    <td colspan="2">Select a Torrent</td>
                </tr>
HTML;
$DB->query("
    SELECT
        ID, GroupID, `Format`, Encoding, HasCue, HasLog, HasLogDB, LogScore, LogChecksum
    FROM torrents
    WHERE HasLog='1' AND HasLogDB='0' AND UserID = ".$LoggedUser['ID']);

if ($DB->has_results()) {
    $GroupIDs = $DB->collect('GroupID');
    $TorrentsInfo = $DB->to_array('ID');
    $Groups = Torrents::get_groups($GroupIDs);

    foreach ($TorrentsInfo as $TorrentID => $Torrent) {
        list($ID, $GroupID, $Format, $Encoding, $HasCue, $HasLog, $HasLogDB, $LogScore, $LogChecksum) = $Torrent;
        $Group = $Groups[(int) $GroupID];
        $GroupName = $Group['Name'];
        $GroupYear = $Group['Year'];
        $ExtendedArtists = $Group['ExtendedArtists'];
        $Artists = $Group['Artists'];
        if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
            unset($ExtendedArtists[2]);
            unset($ExtendedArtists[3]);
            $DisplayName = Artists::display_artists($ExtendedArtists);
        } elseif (!empty($Artists)) {
            $DisplayName = Artists::display_artists([1 => $Artists]);
        } else {
            $DisplayName = '';
        }
        $DisplayName .= '<a href="torrents.php?id='.$GroupID.'&amp;torrentid='.$ID.'" class="tooltip" title="View torrent" dir="ltr">'.$GroupName.'</a>';
        if ($GroupYear > 0) {
            $DisplayName .= " [{$GroupYear}]";
        }
        $Info = [];
        if (!empty($Data['Format'])) {
            $Info[] = $Data['Format'];
        }
        if (!empty($Data['Encoding'])) {
            $Info[] = $Data['Encoding'];
        }
        if (!empty($Info)) {
            $DisplayName .= ' [' . implode('/', $Info) . ']';
        }
        if ($HasLog == '1') {
            $DisplayName .= ' / Log'.($HasLogDB == '1' ? " ({$LogScore}%)" : "");
        }
        if ($HasCue == '1') {
            $DisplayName .= ' / Cue';
        }
        if ($LogChecksum == '0') {
            $DisplayName .= ' / ' . Format::torrent_label('Bad/Missing Checksum');
        }
        echo "\t\t\t\t<tr><td style=\"width: 5%;\"><input type=\"radio\" name=\"torrentid\" value=\"{$ID}\"></td><td>{$DisplayName}</td></tr>";
    }
    $AcceptValues = Logchecker::getAcceptValues();
    echo <<<HTML
                <tr class="colhead">
                    <td colspan="2">Upload Logs for This Torrent</td>
                </tr>
                <tr>
                    <td colspan="2" id="logfields">
                        Check your log files before uploading <a href="logchecker.php" target="_blank">here</a>. For multi-disc releases, click the "<span class="brackets">+</span>" button to add multiple log files.<br />
                        <input id="file" type="file" accept="<?=$AcceptValues?>" name="logfiles[]" size="50" required /> <a href="javascript:;" onclick="AddLogField();" class="brackets">+</a> <a href="javascript:;" onclick="RemoveLogField();" class="brackets">&minus;</a>
                    </td>
                <tr />
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Upload Logs!" name="logsubmit" />
                    </td>
                </tr>
HTML;

}
else {
    echo "\t\t\t\t<tr><td colspan='2'>No uploads found.</td></tr>";
}
echo <<<HTML
            </table>
        </form>
    </div>
</div>
HTML;

View::show_footer();
