<?php
if (!check_perms('site_torrents_notify')) {
    error(403);
}
View::show_header('Manage notifications', 'jquery.validate,form_validate');
?>
<div class="thin">
    <div class="header">
        <h2>Notify me of all new torrents with...</h2>
        <div class="linkbox">
            <a href="torrents.php?action=notify" class="brackets">View notifications</a>
        </div>
    </div>
<?php
$DB->prepared_query("
    SELECT ID,
        Label,
        Artists,
        ExcludeVA,
        NewGroupsOnly,
        Tags,
        NotTags,
        ReleaseTypes,
        Categories,
        Formats,
        Encodings,
        Media,
        FromYear,
        ToYear,
        Users
    FROM users_notify_filters
    WHERE UserID = ?
    ", $LoggedUser['ID']
);

$NumFilters = $DB->record_count();

$Notifications = $DB->to_array();
$Notifications[] = [
    'ID' => false,
    'Label' => '',
    'Artists' => '',
    'ExcludeVA' => false,
    'NewGroupsOnly' => true,
    'Tags' => '',
    'NotTags' => '',
    'ReleaseTypes' => '',
    'Categories' => '',
    'Formats' => '',
    'Encodings' => '',
    'Media' => '',
    'FromYear' => '',
    'ToYear' => '',
    'Users' => ''
];

$userMan = new Gazelle\Manager\User;
$i = 0;
foreach ($Notifications as $N) { // $N stands for Notifications
    $i++;
    $NewFilter = $N['ID'] === false;
    $N['Artists']      = implode("\n", explode('|', substr($N['Artists'], 1, -1)));
    $N['Tags']         = implode("\n", explode('|', substr($N['Tags'], 1, -1)));
    $N['NotTags']      = implode("\n", explode('|', substr($N['NotTags'], 1, -1)));
    $N['ReleaseTypes'] = explode('|', substr($N['ReleaseTypes'], 1, -1));
    $N['Categories']   = explode('|', substr($N['Categories'], 1, -1));
    $N['Formats']      = explode('|', substr($N['Formats'], 1, -1));
    $N['Encodings']    = explode('|', substr($N['Encodings'], 1, -1));
    $N['Media']        = explode('|', substr($N['Media'], 1, -1));
    $N['Users']        = explode('|', substr($N['Users'], 1, -1));

    $Usernames = [];
    foreach ($N['Users'] as $UserID) {
        $u = $userMan->findById((int)$UserID);
        if (!is_null($u)) {
            $Usernames[] = $u->username();
        }
    }
    $Usernames = implode("\n", $Usernames);

    if ($N['FromYear'] == 0) {
        $N['FromYear'] = '';
    }
    if ($N['ToYear'] == 0) {
        $N['ToYear'] = '';
    }
    if ($NewFilter && $NumFilters > 0) {
?>
    <br /><br />
    <h3>Create a new notification filter</h3>
<?php
    } elseif ($NumFilters > 0) { ?>
    <h3>
        <a href="feeds.php?feed=torrents_notify_<?=$N['ID']?>_<?=$LoggedUser['torrent_pass']?>&amp;user=<?=$LoggedUser['ID']?>&amp;auth=<?=$LoggedUser['RSS_Auth']?>&amp;passkey=<?=$LoggedUser['torrent_pass']?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;name=<?=urlencode($N['Label'])?>"><img src="<?=STATIC_SERVER?>/common/symbols/rss.png" alt="RSS feed" /></a>
        <?=display_str($N['Label'])?>
        <a href="user.php?action=notify_delete&amp;id=<?=$N['ID']?>&amp;auth=<?=$LoggedUser['AuthKey']?>" onclick="return confirm('Are you sure you want to delete this notification filter?')" class="brackets">Delete</a>
        <a href="#" onclick="$('#filter_<?=$N['ID']?>').gtoggle(); return false;" class="brackets">Show</a>
    </h3>
<?php
    } ?>
    <form class="<?=($NewFilter ? 'create_form' : 'edit_form')?>" id="<?=($NewFilter ? 'filter_form' : '')?>" name="notification" action="user.php" method="post">
        <input type="hidden" name="formid" value="<?=$i?>" />
        <input type="hidden" name="action" value="notify_handle" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
<?php
    if (!$NewFilter) { ?>
        <input type="hidden" name="id<?=$i?>" value="<?=$N['ID']?>" />
<?php
    } ?>
        <table <?=(!$NewFilter ? 'id="filter_'.$N['ID'].'" class="layout hidden"' : 'class="layout"')?>>
<?php
    if ($NewFilter) { ?>
            <tr>
                <td class="label"><strong>Notification filter name</strong></td>
                <td>
                    <input type="text" class="required" name="label<?=$i?>" style="width: 100%;" />
                    <p class="min_padding">A name for the notification filter set to tell different filters apart.</p>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <strong>All fields below here are optional</strong>
                </td>
            </tr>
<?php
    } ?>
            <tr>
                <td class="label"><strong>One of these artists</strong></td>
                <td>
                    <textarea name="artists<?=$i?>" style="width: 100%;" rows="5"><?=display_str($N['Artists'])?></textarea>
                    <p class="min_padding">Newline-separated list of artists</p>
                    <input type="checkbox" name="excludeva<?=$i?>" id="excludeva_<?=$N['ID']?>"<?php if ($N['ExcludeVA'] == '1') { echo ' checked="checked"';} ?> />
                    <label for="excludeva_<?=$N['ID']?>">Exclude Various Artists releases</label>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>One of these users</strong></td>
                <td>
                    <textarea name="users<?=$i?>" style="width: 100%;" rows="5"><?=display_str($Usernames)?></textarea>
                    <p class="min_padding">Newline-separated list of usernames</p>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>At least one of these tags</strong></td>
                <td>
                    <textarea name="tags<?=$i?>" style="width: 100%;" rows="2"><?=display_str($N['Tags'])?></textarea>
                    <p class="min_padding">Newline-separated list</p>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>None of these tags</strong></td>
                <td>
                    <textarea name="nottags<?=$i?>" style="width: 100%;" rows="2"><?=display_str($N['NotTags'])?></textarea>
                    <p class="min_padding">Newline-separated list</p>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Only these categories</strong></td>
                <td>
<?php   foreach ($Categories as $Category) { ?>
                    <input type="checkbox" name="categories<?=$i?>[]" id="<?=$Category?>_<?=$N['ID']?>" value="<?=$Category?>"<?php if (in_array($Category, $N['Categories'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$Category?>_<?=$N['ID']?>"><?=$Category?></label>
<?php   } ?>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Only these types</strong></td>
                <td>
<?php
        $releaseTypes = (new Gazelle\ReleaseType)->list();
        foreach ($releaseTypes as $ReleaseType) {
?>
                    <input type="checkbox" name="releasetypes<?=$i?>[]" id="<?=$ReleaseType?>_<?=$N['ID']?>" value="<?=$ReleaseType?>"<?php if (in_array($ReleaseType, $N['ReleaseTypes'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$ReleaseType?>_<?=$N['ID']?>"><?=$ReleaseType?></label>
<?php   } ?>
               </td>
            </tr>
            <tr>
                <td class="label"><strong>Only these formats</strong></td>
                <td>
<?php   foreach ($Formats as $Format) { ?>
                    <input type="checkbox" name="formats<?=$i?>[]" id="<?=$Format?>_<?=$N['ID']?>" value="<?=$Format?>"<?php if (in_array($Format, $N['Formats'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$Format?>_<?=$N['ID']?>"><?=$Format?></label>
<?php   } ?>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Only these bitrates</strong></td>
                <td>
<?php   foreach ($Bitrates as $Bitrate) { ?>
                    <input type="checkbox" name="bitrates<?=$i?>[]" id="<?=$Bitrate?>_<?=$N['ID']?>" value="<?=$Bitrate?>"<?php if (in_array($Bitrate, $N['Encodings'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$Bitrate?>_<?=$N['ID']?>"><?=$Bitrate?></label>
<?php   } ?>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Only these media</strong></td>
                <td>
<?php   foreach ($Media as $Medium) { ?>
                    <input type="checkbox" name="media<?=$i?>[]" id="<?=$Medium?>_<?=$N['ID']?>" value="<?=$Medium?>"<?php if (in_array($Medium, $N['Media'])) { echo ' checked="checked"';} ?> />
                    <label for="<?=$Medium?>_<?=$N['ID']?>"><?=$Medium?></label>
<?php   } ?>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Between the years</strong></td>
                <td>
                    <input type="text" name="fromyear<?=$i?>" value="<?=$N['FromYear']?>" size="6" />
                    and
                    <input type="text" name="toyear<?=$i?>" value="<?=$N['ToYear']?>" size="6" />
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Only new releases</strong></td>
                <td>
                    <input type="checkbox" name="newgroupsonly<?=$i?>" id="newgroupsonly_<?=$N['ID']?>"<?php if ($N['NewGroupsOnly'] == '1') { echo ' checked="checked"';} ?> />
<label for="newgroupsonly_<?=$N['ID']?>">Only notify for new releases, not new formats</label>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="<?=($NewFilter ? 'Create filter' : 'Update filter')?>" />
                </td>
            </tr>
        </table>
    </form>
<?php } ?>
</div>
<?php
View::show_footer();
