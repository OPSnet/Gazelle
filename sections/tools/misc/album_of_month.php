<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

//Make sure the form was sent
if (isset($_POST['GroupID'])) {
    authorize();

    // match an ID or the last id in a URL (e.g. torrent group)
    if (preg_match('/(\d+)\s*$/', $_POST['GroupID'], $match)) {
        $GroupID = $match[1];
    } else {
        error('You did not enter a valid GroupID');
    }

    $FreeLeechType = $_POST['freeleechtype'];
    $FreeLeechReason = $_POST['freeleechreason'];
    if (!in_array($FreeLeechType, ['0', '1', '2']) || !in_array($FreeLeechReason, ['0', '1', '2', '3'])) {
        error('Invalid freeleech type or freeleech reason');
    } else {
        $DB->prepared_query('
            SELECT
                tg.ID,
                tg.ArtistID,
                tg.Name,
                tg.WikiImage,
                ag.Name AS Artist
            FROM torrents_group AS tg
            LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            LEFT JOIN artists_group AS ag ON (ag.ArtistID = ta.ArtistID)
            WHERE tg.id = ?', $GroupID);

        $Album = $DB->next_record();

        //Make sure album exists
        if (!$Album['ID']) {
            error('Please supply a valid album ID');
        } else {
            //Remove old albums with type = 0 (so we remove the previous AotM)
            $DB->prepared_query('
                UPDATE featured_albums SET
                    Ended = now()
                WHERE
                    Ended IS NULL
                    AND Type = 0
            ');
            $Cache->delete_value('album_of_the_month');

            //Freeleech torrents
            if (isset($_POST['FLTorrents'])) {
                $DB->prepared_query("
                    SELECT ID
                    FROM torrents
                    WHERE Encoding = 'Lossless' AND GroupID = ?", $Album['ID']);
                $TorrentIDs = $DB->collect('ID');

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
                                AND ID IN (" . placeholders($TorrentIDs) . ")
                            ", Format::get_bytes($Size . $Units), ...$TorrentIDs
                        );
                        $LargeTorrents = $DB->collect('ID');
                        $TorrentIDs = array_diff($TorrentIDs, $LargeTorrents);
                    }
                }

                if (count($TorrentIDs) > 0) {
                    Torrents::freeleech_torrents($TorrentIDs, $FreeLeechType, $FreeLeechReason);
                }

                if (isset($LargeTorrents) && count($LargeTorrents) > 0) {
                    Torrents::freeleech_torrents($LargeTorrents, '2', $FreeLeechReason);
                }
            }

            //Get post title (album title)
            if ($Album['ArtistID'] != '0') {
                $Title = $Album['Artist'] . ' - ' . $Album['Name'];
            } else {
                $Title = $Album['Name'];
            }

            //Get post body
            if (isset($_POST['Body']) && $_POST['Body'] != '') {
                $Body = $_POST['Body'];
            } else {
                $Body = '[size=4]' . $Title . '[/size]' . "\n\n";
                if (!empty($Album['WikiImage']))
                    $Body .= '[img]' . $Album['WikiImage'] . '[/img]';
            }

            //Add album of the month and create forum
            $forum = new Gazelle\Forum(AOTM_FORUM_ID);
            $DB->prepared_query('
                INSERT INTO featured_albums
                       (GroupID, ThreadID, Type)
                VALUES (?,       ?,        0)
                ', $GroupID, $forum->addThread($Viewer->id(), $Title, $Body)
            );
            header("Location: /");
        }
    }
}
View::show_header('Album of the Month');
?>
    <div class="header">
        <h2>Album of the Month</h2>
    </div>

    <div class="thin box pad">
    <form class="create_form" name="album" method="post" action="">
        <div class="pad">
            <input type="hidden" name="action" value="monthalbum" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <h3>Torrent</h3>
            (enter a torrent group ID or URL)<br />
            <input type="text" name="GroupID" id="groupid" class="inputtext" /><br /><br />
            <h3>Body</h3>
            (Leave blank to auto generate)
            <textarea name="Body" cols="95" rows="15"></textarea><br /><br />
            <input type="checkbox" name="FLTorrents" checked />&nbsp;Mark lossless torrents as&nbsp;
            <select name="freeleechtype">
                <option value="1" selected>FL</option>
                <option value="2" >NL</option>
                <option value="0" >Normal</option>
            </select>
            &nbsp;for reason&nbsp;<select name="freeleechreason">
<?php      $FL = ['N/A', 'Staff Pick', 'Perma-FL', 'Vanity House'];
        foreach ($FL as $Key => $FLType) { ?>
                            <option value="<?=$Key?>" <?=$FLType == 'Staff Pick' ? 'selected' : ''?>><?=$FLType?></option>
<?php   } ?>
            </select><br /><br />
            <input type="checkbox" name="NLOver" checked />&nbsp;NL Torrents over <input type="text" name="size" value="<?=isset($_POST['size']) ? $_POST['size'] : '1'?>" size=1 />
            <select name="scale">
                <option value="k">KiB</option>
                <option value="m">MiB</option>
                <option value="g" selected>GiB</option>
            </select><br /><br />

            <div class="center">
                <input type="submit" name="submit" value="Submit" class="submit" />
            </div>
        </div>
    </form>
    </div>
<?php
View::show_footer();
