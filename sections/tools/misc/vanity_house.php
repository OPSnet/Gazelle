<?php
if (!check_perms('users_mod')) {
    error(403);
}

//Make sure the form was sent
if (isset($_POST['GroupID'])) {
    authorize();

    //Vanity House forum ID
    $ForumID = 18;

    $GroupID = trim($_POST['GroupID']);

    if (!is_number($GroupID)) {
        error('You did not enter a valid GroupID');
    }

    $FreeLeechType = (int) $_POST['freeleechtype'];
    $FreeLeechReason = (int) $_POST['freeleechreason'];
    if (!in_array($FreeLeechType, array(0, 1, 2)) || !in_array($FreeLeechReason, array(0, 1, 2, 3))) {
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
                LEFT JOIN artists_group AS ag ON tg.ArtistID = ag.ArtistID
            WHERE tg.id = ?', $GroupID);

        $Album = $DB->next_record();

        //Make sure album exists
        if (is_number($Album['ID'])) {
            //Remove old albums with type = 1, (so we remove previous VH alubm)
            $DB->prepared_query('DELETE FROM featured_albums WHERE Type = 1');
            $Cache->delete_value('vanity_house_album');

            //Freeleech torrents
            if (isset($_POST['FLTorrents'])) {
                $DB->prepared_query('
                    SELECT ID
                    FROM torrents
                    WHERE GroupID = ?', $Album['ID']);
                $TorrentIDs = $DB->collect('ID');

                if (isset($_POST['NLOver']) && $FreeLeechType == 1) {
                    // Only use this checkbox if freeleech is selected
                    $Size = (int) $_POST['size'];
                    $Units = db_string($_POST['scale']);

                    if (empty($Size) || !in_array($Units, array('k', 'm', 'g'))) {
                        $Err = 'Invalid size or units';
                    } else {
                        $Bytes = Format::get_bytes($Size . $Units);

                        $DB->query("
                            SELECT ID
                            FROM torrents
                            WHERE ID IN (".implode(', ', $TorrentIDs).")
                              AND Size > '$Bytes'");
                        $LargeTorrents = $DB->collect('ID');
                        $TorrentIDs = array_diff($TorrentIDs, $LargeTorrents);
                    }
                }

                if (sizeof($TorrentIDs) > 0) {
                    Torrents::freeleech_torrents($TorrentIDs, $FreeLeechType, $FreeLeechReason);
                }

                if (isset($LargeTorrents) && sizeof($LargeTorrents) > 0) {
                    Torrents::freeleech_torrents($LargeTorrents, 2, $FreeLeechReason);
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

            //Create forum post
            $ThreadID = Misc::create_thread($ForumID, $LoggedUser[ID], $Title, $Body);

            //Add VH album
            $type = 1;
            $DB->prepared_query('
                INSERT INTO featured_albums
                    (GroupID,ThreadID,Started,Type)
                VALUES
                    (?, ?, ?, ?)', db_string($GroupID), $ThreadID, sqltime(), 1);


            //Redirect to home page
            header("Location: /");
        //What to do if we don't have a GroupID
        } else {
            //Uh oh, something went wrong
            error('Please supply a valid album ID');
        }
    }
//Form wasn't sent -- Show form
} else {

    //Show our beautiful header
    View::show_header('Vanity House');

?>
    <div class="header">
        <h2>Vanity House</h2>
    </div>

    <div class="thin box pad">
    <form class="create_form" name="album" method="post" action="">
        <div class="pad">
            <input type="hidden" name="action" value="vanityhouse" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <h3>Album ID</h3>
            <input type="text" name="GroupID" id="groupid" class="inputtext" />    <br />
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
<?php      $FL = array('N/A', 'Staff Pick', 'Perma-FL', 'Vanity House');
        foreach ($FL as $Key => $FLType) { ?>
                            <option value="<?=$Key?>" <?=$FLType == 'Staff Pick' ? 'selected' : ''?>><?=$FLType?></option>
<?php   } ?>
            </select><br /><br />
            <input type="checkbox" name="NLOver" />&nbsp;NL Torrents over <input type="text" name="size" value="<?=isset($_POST['size']) ? $_POST['size'] : '1'?>" size=1 />
            <select name="scale">
                <option value="k">KB</option>
                <option value="m">MB</option>
                <option value="g" selected>GB</option>
            </select><br /><br />

            <div class="center">
                <input type="submit" name="submit" value="Submit" class="submit" />
            </div>
        </div>
    </form>
    </div>
<?php

     View::show_footer();
}

?>
