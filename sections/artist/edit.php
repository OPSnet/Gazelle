<?php

if (!check_perms('torrents_edit')) {
    error(403);
}

try {
    $artist = new Gazelle\Artist((int)$_GET['artistid']);
}
catch (Exception $e) {
    error("Cannot find an artist with the ID {$artistId}: See the <a href=\"log.php?search=Artist+$artistId\">site log</a>.");
}
$artistId = $artist->id();

// Get the artist name and the body of the last revision
list($name, $image, $body, $vanityHouse, $discogsId) = $artist->editableInformation();
if (!$name) {
    error("Cannot find an artist with the ID {$artistId}: See the <a href=\"log.php?search=Artist+$artistId\">site log</a>.");
}

// Start printing form
View::show_header('Edit artist');
?>
<div class="thin">

<div class="header">
    <h2>Edit <a href="artist.php?id=<?=$artistId?>"><?=$name?></a></h2>
</div>

<form class="edit_form" name="artist" action="artist.php" method="post">
<input type="hidden" name="action" value="edit" />
<input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<input type="hidden" name="artistid" value="<?= $artistId?>" />
<table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border artist_edit" id="artist_edit_basic">
    <tr class="colhead_dark">
        <td colspan="2"><strong>Basic Information</strong></td>
    </tr>
    <tr>
        <td class="label" style="width: 120px; vertical-align: top;">Image</td>
        <td>
        <input type="text" name="image" size="92" value="<?= $image ?>" /><br />
<?php if (IMAGE_HOST_BANNED) { ?>
        <b>Images hosted on <strong class="important_text"><?= implode(', ', IMAGE_HOST_BANNED)
            ?> are not allowed</strong>, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>.</b><br />
<?php } ?>
        </td>
    </tr>
    <tr>
        <td class="label" style="vertical-align: top;">Discogs ID</td>
        <td>
        <div class="pad">E.g. for Suzanne Vega, the Discogs artist page is https://www.discogs.com/artist/41182-Suzanne-Vega
        <br />Hence her Discogs ID is <b>41182</b>.
        </div>
        <input type="text" name="discogs-id" size="9" value="<?= $discogsId ?>" /><br /><br />
        </td>
    </tr>
    <tr>
        <td class="label" style="vertical-align: top;">Artist information</td>
        <td>
           <?= (new Gazelle\Util\Textarea('body', display_str($body), 80, 10))->emit() ?><br /><br />
        </td>
    </tr>
    <tr>
        <td class="label" style="vertical-align: top;"><label for="vanity_house">Vanity House</label></td>
        <td>
            <input type="checkbox" id="vanity_house" name="vanity_house" value="1"<?=
                check_perms('artist_edit_vanityhouse') ? '' : ' disabled="disabled"' ?><?=($vanityHouse ? ' checked="checked"' : '')?> /><br /><br />
        </td>
    </tr>
    <tr>
        <td class="label" style="vertical-align: top;">Edit summary</td>
        <td>
        <input type="text" name="summary" size="92" /><br /><br />
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" value="Edit Artist" /></td>
    </tr>
</table>
</form>

<?php if (check_perms('torrents_edit')) { ?>

<form class="merge_form" name="artist" action="artist.php" method="post">
<input type="hidden" name="action" value="change_artistid" />
<input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<input type="hidden" name="artistid" value="<?= $artistId ?>" />
    <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border artist_edit" id="artist_edit_redirect">
    <tr class="colhead_dark">
        <td colspan="2"><strong>Alias Management</strong></td>
    </tr>
    <tr>
        <td class="label" style="width: 120px; vertical-align: top;">Existing aliases</td>
        <td>
        <div class="pad">
        <ul class="nobullet">
<?php
$nonRedirAliases = $artist->redirects();
$alias = [];
foreach($nonRedirAliases as $r) {
    if ($r['aliasName'] == $name) {
        $defaultId = $r['aliasId'];
    }
?>
            <li>
                <span class="tooltip" title="Alias ID"><?= $r['aliasId'] ?></span>. <span class="tooltip" title="Alias name"><?= $r['aliasName'] ?></span>
<?php if ($r['redirectId']) { ?>
                (writes redirect to <span class="tooltip" title="Target alias ID"><?= $r['redirectId'] ?></span>)
<?php
    } else {
        $alias[$r['aliasId']] = $r['aliasName'];
    }
?>
<?php if ($r['userId']) { ?>
                &nbsp;<a href="user.php?id=<?= $r['userId'] ?>" class="brackets tooltip">Added by <?= Users::user_info($r['userId'])['Username'] ?></a>
<?php } ?>
                &nbsp;<a href="artist.php?action=delete_alias&amp;aliasid=<?=$r['aliasId']?>&amp;auth=<?= $Viewer->auth() ?>" title="Delete this alias" class="brackets tooltip">X</a>
<?php if (!$r['redirectId']) { ?>
                &nbsp;<?= "\xE2\x98\x85" ?>
<?php } ?>
            </li>
<?php } ?>
        </ul>
        </div>
        </td>
    </tr>
</table>
</form>

<form class="add_form" name="aliases" action="artist.php" method="post">
<input type="hidden" name="action" value="add_alias" />
<input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<input type="hidden" name="artistid" value="<?=$artistId?>" />
<table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border artist_edit" id="artist_edit_alias">
    <tr>
        <td class="label" style="width: 120px; vertical-align: top;">Add new alias</td>
        <td>
        <div class="pad">
        <p>This autocorrects artist names as they are written (e.g. when new torrents are uploaded or artists added). All uses of this new alias will be recorded as the alias ID you enter here. Use for common misspellings, inclusion of diacritical marks, etc.</p>
            <div class="field_div">
                <span class="label"><strong>Name:</strong></span>
                <br />
                <input type="text" name="name" size="40" value="<?=$name?>" />
            </div>
            <div class="field_div">
                <span class="label"><strong>Writes redirect to:</strong></span>
                <select name="redirect">
<?php foreach ($alias as $aliasId => $aliasName) { ?>
                    <option value="<?= $aliasId ?>"<?= $aliasId == $defaultId ? ' selected="selected"' : "" ?>><?= $aliasName ?></option>
<?php } ?>
                    <option value="0">Non-redirecting alias</option>
                </select><br />
            </div>
        </div>
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" value="Add alias" /></td>
    </tr>
</table>
</form>

<form class="merge_form" name="artist" action="artist.php" method="post">
<input type="hidden" name="action" value="change_artistid" />
<input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<input type="hidden" name="artistid" value="<?= $artistId ?>" />
<table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border artist_edit" id="artist_edit_redirect">
    <tr>
        <td class="label" style="width: 120px; vertical-align: top;">Change to non-redirecting alias</td>
        <td>
            <p>Merges this artist ("<?=$name?>") into the artist specified below (without redirection),
            so that ("<?=$name?>") and its aliases will appear as a non-redirecting alias of the artist entered in the text box below.</p>
            <p>A non-redirecting alias is used so a release is show with the correct artist name (e.g.
            <i>Sun Ra All Stars</i> versus <i>Sun Ra and His Astro-Solar-Infinity Arkestra</i>) and all
            releases are shown on the artist page <i>Sun Ra</i>.</p>
            <br />
            <div style="text-align: center;">
                <label for="newartistid">Artist ID:</label>&nbsp;<input type="text" id="newartistid" name="newartistid" size="40" value="" /><br />
                <strong>OR</strong><br />
                <label for="newartistid">Artist name:</label>&nbsp;<input type="text" id="newartistname" name="newartistname" size="40" value="" />
            </div><br /><br />
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" value="Make non-redirecting alias" /></td>
    </tr>
</table>
</form>

<form class="rename_form" name="artist" action="artist.php" method="post">
<input type="hidden" name="action" value="rename" />
<input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
<input type="hidden" name="artistid" value="<?= $artistId ?>" />
    <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border artist_edit" id="artist_edit_rename">
    <tr class="colhead_dark">
        <td colspan="2"><strong>Rename this artist</strong></td>
    </tr>
    <tr>
        <td class="label" style="width: 120px; vertical-align: top;">New name</td>
        <td>
            <input type="text" name="name" size="92" value="<?=$name?>" /><br /><br />
        </td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td><input type="submit" value="Rename Artist" /></td>
    </tr>
    </table>
</form>

<?php } /* check_perms('torrents_edit') */ ?>
</div>
<?php

View::show_footer();
