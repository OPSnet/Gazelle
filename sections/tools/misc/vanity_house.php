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

    if (!is_number($GroupID)) {
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
            //Remove old albums with type = 1, (so we remove previous VH alubm)
            $DB->prepared_query('
                UPDATE featured_albums SET
                    Ended = now()
                WHERE
                    Ended IS NULL
                    AND Type = 1
            ');
            $Cache->delete_value('vanity_house_album');

            //Freeleech torrents
            if (isset($_POST['FLTorrents'])) {
                $DB->prepared_query("
                    SELECT ID
                    FROM torrents
                    WHERE Encoding = 'Lossless' AND GroupID = ?
                    ", $Album['ID']
                );
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

                $torMan = new Gazelle\Manager\Torrent;
                if ($TorrentIDs) {
                    $torMan->setFreeleech($Viewer, $TorrentIDs, $FreeLeechType, $FreeLeechReason, false, true);
                }
                if ($LargeTorrents) {
                    $torMan->setFreeleech($Viewer, $LargeTorrents, '2', $FreeLeechReason, false, true);
                }
            }

            //Get post title (album title)
            if ($Album['ArtistID'] != '0') {
                $title = "{$Album['Artist']} \xE2\x80\x93  {$Album['Name']}";
            } else {
                $title = $Album['Name'];
            }

            //Get post body
            if (isset($_POST['Body']) && trim($_POST['Body']) != '') {
                $body = trim($_POST['Body']);
            } else {
                $body = '[size=4]' . $title . '[/size]' . "\n\n";
                if (!empty($Album['WikiImage']))
                    $body .= '[img]' . $Album['WikiImage'] . '[/img]';
            }

            // create forum and add Showcase album
            $forum = new Gazelle\Forum(VANITY_HOUSE_FORUM_ID);
            $thread = (new Gazelle\Manager\ForumThread)->create(
                forumId: $forum->id(),
                userId:  $Viewer->id(),
                title:   $title,
                body:    $body,
            );
            $DB->prepared_query("
                INSERT INTO featured_albums
                       (GroupID, ThreadID, Type)
                VALUES (?,       ?,        1)
                ", $GroupID, $thread->id()
            );

            header("Location: /");
            exit;
        }
    }
}
View::show_header('Vanity House');
?>

    <div class="header">
        <h2>Vanity House</h2>
    </div>

    <div class="thin box pad">
    <form class="create_form" name="album" method="post" action="">
        <div class="pad">
            <input type="hidden" name="action" value="vanityhouse" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <h3>Torrent</h3>
            (enter a torrent group ID or URL)<br />
            <input type="text" name="GroupID" id="groupid" class="inputtext" /><br /><br />

            <h3>Body</h3>
            (Leave blank to auto generate)
            <textarea name="Body" cols="95" rows="15"></textarea><br /><br />
            <input type="checkbox" name="FLTorrents" />&nbsp;Mark torrents as&nbsp;
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
            <input type="checkbox" name="NLOver" />&nbsp;NL Torrents over <input type="text" name="size" value="<?= $_POST['size'] ?? 1 ?>" size=1 />
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
