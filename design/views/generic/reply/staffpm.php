        <div id="compose" class="<?=($Hidden ? 'hidden' : '')?>">
            <form class="send_form" name="staff_message" action="staffpm.php" method="post">
                <input type="hidden" name="action" value="takepost" />
                <h3><label for="subject">Subject</label></h3>
                <input size="95" type="text" name="subject" id="subject" required/>
                <br />

                <h3><label for="message">Message</label></h3>
<?php
                $TextPrev = new TEXTAREA_PREVIEW('message', 'message', '', 95, 10, true, false, false, [], true);
                list($Classes, $ClassLevels) = Users::get_classes();
                $ForumMod = $Classes[FORUM_MOD]['Level'];
                $Staff = $Classes[MOD]['Level'];
?>
                <br />

                <strong>Send to: </strong>
                <select name="level">
<?php           if (!isset(G::$LoggedUser['LockedAccount'])) { ?>
                    <option value="0" selected="selected">First Line Support</option>
                    <option value="<?=$ForumMod?>">Forum Moderators</option>
<?php           } ?>
                    <option value="<?=$Staff?>">Staff</option>
                </select>

                <input type="button" value="Preview" class="hidden button_preview_<?=$TextPrev->getID()?>" />
                <input type="submit" value="Send message" />
                <input type="button" value="Hide" onclick="$('#compose').gtoggle(); return false;" />
            </form>
        </div>
