<?php
if (!check_perms('users_mod')) {
    error(403);
}

View::show_header('Multiple Freeleech');

if (isset($_POST['torrents'])) {
    $GroupIDs = [];
    $Elements = explode("\r\n", $_POST['torrents']);
    foreach ($Elements as $Element) {
        // Get all of the torrent IDs
        if (strpos($Element, "torrents.php") !== false) {
            $Data = explode("id=", $Element);
            if (!empty($Data[1])) {
                $GroupIDs[] = (int) $Data[1];
            }
        } else if (strpos($Element, "collages.php") !== false) {
            $Data = explode("id=", $Element);
            if (!empty($Data[1])) {
                $CollageID = (int) $Data[1];
                $DB->prepared_query('
                    SELECT GroupID
                    FROM collages_torrents
                    WHERE CollageID = ?
                    ', $CollageID);
                $GroupIDs = array_merge($GroupIDs, $DB->collect('GroupID'));
            }
        }
    }

    if (sizeof($GroupIDs) == 0) {
        $Err = 'Please enter properly formatted URLs';
    } else {
        $FreeLeechType = $_POST['freeleechtype'];
        $FreeLeechReason = $_POST['freeleechreason'];

        if (!in_array($FreeLeechType, ['0', '1', '2']) || !in_array($FreeLeechReason, ['0', '1', '2', '3'])) {
            $Err = 'Invalid freeleech type or freeleech reason';
        } else {
            // Get the torrent IDs
            $DB->prepared_query("
                SELECT ID
                FROM torrents
                WHERE GroupID IN (" . placeholders($GroupIDs) . ")
                ", ...$GroupIDs
            );
            $TorrentIDs = $DB->collect('ID');

            if (sizeof($TorrentIDs) == 0) {
                $Err = 'Invalid group IDs';
            } else {
                if (isset($_POST['NLOver']) && $FreeLeechType == '1') {
                    // Only use this checkbox if freeleech is selected
                    $Size = (int) $_POST['size'];
                    $Units = trim($_POST['scale']);

                    if (empty($Size) || !in_array($Units, ['k', 'm', 'g'])) {
                        $Err = 'Invalid size or units';
                    } else {
                        $DB->prepared_query("
                             SELECT ID
                             FROM torrents
                             WHERE Size > ?
                                  AND ID IN (" . placeholders($TorrentIDs) . ")",
                              Format::get_bytes($Size . $Units), ...$TorrentIDs
                        );
                        $LargeTorrents = $DB->collect('ID');
                        $TorrentIDs = array_diff($TorrentIDs, $LargeTorrents);
                    }
                }

                if (sizeof($TorrentIDs) > 0) {
                    Torrents::freeleech_torrents($TorrentIDs, $FreeLeechType, $FreeLeechReason);
                }

                if (isset($LargeTorrents) && sizeof($LargeTorrents) > 0) {
                    Torrents::freeleech_torrents($LargeTorrents, '2', $FreeLeechReason);
                }

                $Err = 'Done!';
            }
        }
    }
}
?>
<div class="thin">
    <div class="box pad box2">
<?php  if (isset($Err)) { ?>
        <strong class="important_text"><?=$Err?></strong><br />
<?php  } ?>
        Paste a list of collage or torrent group URLs
    </div>
    <div class="box pad">
        <form class="send_form center" action="" method="post">
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <textarea name="torrents" style="width: 95%; height: 200px;"><?=$_POST['torrents']?></textarea><br /><br />
            Mark torrents as:&nbsp;
            <select name="freeleechtype">
                <option value="1" <?=$_POST['freeleechtype'] == '1' ? 'selected' : ''?>>FL</option>
                <option value="2" <?=$_POST['freeleechtype'] == '2' ? 'selected' : ''?>>NL</option>
                <option value="0" <?=$_POST['freeleechtype'] == '0' ? 'selected' : ''?>>Normal</option>
            </select>
            &nbsp;for reason&nbsp;<select name="freeleechreason">
<?php   $FL = ['N/A', 'Staff Pick', 'Perma-FL', 'Vanity House'];
        foreach ($FL as $Key => $FLType) { ?>
                            <option value="<?=$Key?>" <?=$_POST['freeleechreason'] == $Key ? 'selected' : ''?>><?=$FLType?></option>
<?php   } ?>
            </select><br /><br />
            <input type="checkbox" name="NLOver" checked />&nbsp;NL Torrents over <input type="text" name="size" value="<?=isset($_POST['size']) ? $_POST['size'] : '1'?>" size=1 />
            <select name="scale">
                <option value="k" <?=$_POST['scale'] == 'k' ? 'selected' : ''?>>KiB</option>
                <option value="m" <?=$_POST['scale'] == 'm' ? 'selected' : ''?>>MiB</option>
                <option value="g" <?=!isset($_POST['scale']) || $_POST['scale'] == 'g' ? 'selected' : ''?>>GiB</option>
            </select><br /><br />
            <input type="submit" value="Submit" />
        </form>
    </div>
</div>
<?php
View::show_footer();
