<?php
if (!check_perms('admin_recovery')) {
    error(403);
}
$recovery = new Gazelle\Recovery;

function security_checksum($a, $b) {
    return sha1(implode(chr(1), [RECOVERY_PAIR_SALT, implode(chr(2), $a), implode(chr(3), $b)]));
}

if (isset($_POST['curr']) && isset($_POST['prev'])) {
    $curr_id = (int)trim($_POST['curr']);
    $curr = (new Gazelle\Manager\User)->findById($curr_id);
    if (!$curr) {
        $Result = "No current ID found for <tt>$curr_id</tt>.";
    } else {
        $prev_id = (int)trim($_POST['prev']);
        $prev = $recovery->findById($prev_id);
        if (!$prev) {
            $Result = "No previous ID found for <tt>$prev_id</tt>.";
        } elseif ($Map = $recovery->isMapped($prev_id)) {
            $ID = $Map[0]['ID'];
            $Result = "Previous id $prev_id already mapped to " . \Users::format_username($ID);
        } elseif ($Map = $recovery->isMappedLocal($curr_id)) {
            $ID = $Map[0]['ID'];
            $Result = \Users::format_username($curr_id) . " is already mapped to previous id $ID";
        } else {
            [$Prev, $Confirm] = $recovery->pairConfirmation($prev_id, $curr_id);
            if (!($Prev && $Confirm)) {
                $Result = "No database information to pair from $prev_id to $curr_id";
            }
            if (array_key_exists('check', $_POST)) {
                if ($_POST['check'] != security_checksum($prev_id, $curr_id)) {
                    $Result = "Security checksum failed";
                }
                else {
                    $Result = $recovery->mapToPrevious($curr_id, $prev_id, $LoggedUser['Username'])
                        ? \Users::format_username($curr_id) . " has been successfully mapped to previous user " .$Confirm['Username'] . "."
                        : "DB Error: could not map $curr_id to $prev_id"
                        ;
                    unset($Confirm);
                }
            }
        }
    }
}

View::show_header('Recovery pair users');
?>
<div class="thin">

<div class="linkbox">
    <a class="brackets" href="/recovery.php?action=admin&amp;state=pending">Pending</a>
    <a class="brackets" href="/recovery.php?action=admin&amp;state=validated">Validated</a>
    <a class="brackets" href="/recovery.php?action=admin&amp;state=accepted">Accepted</a>
    <a class="brackets" href="/recovery.php?action=admin&amp;state=denied">Denied</a>
    <a class="brackets" href="/recovery.php?action=admin&amp;state=claimed">Your claimed</a>
    <a class="brackets" href="/recovery.php?action=browse">Browse</a>
    <a class="brackets" href="/recovery.php?action=pair">Pair</a>
</div>

<?php if (isset($Result)) { ?>
<div class="box">
    <div class="head">Result</div>
    <div><?= $Result ?></div>
</div>
<?php } elseif (isset($Confirm)) { ?>
<div class="box">
    <div class="head">Confirm</div>

    <form method="post" action="/recovery.php?action=pair">
    <p>Please confirm the following pairing:<p>
    <table>
        <tr><th></th><th>Previous</th><th><?= SITE_NAME ?></th>

        <tr>
            <th>ID</th>
            <td><?= $Prev['ID'] ?></td>
            <td><?= $Confirm['ID'] ?></td>
        </tr>

        <tr>
            <th>Username</th>
            <td><?= $Prev['Username'] ?></td>
            <td><?= $Confirm['Username'] ?></td>
        </tr>

        <tr>
            <th>Userclass</th>
            <td><?= $Prev['UserClass'] ?></td>
            <td><?= $Confirm['UserClass'] ?></td>
        </tr>

        <tr>
            <th>Email</th>
            <td><?= $Prev['Email'] ?></td>
            <td><?= $Confirm['Email'] ?></td>
        </tr>

        <tr>
            <th>Announce</th>
            <td><?= $Prev['torrent_pass'] ?></td>
            <td><?= $Confirm['torrent_pass'] ?></td>
        </tr>

        <tr>
            <th>Torrents</th>
            <td><?= $Prev['nr_torrents'] ?></td>
            <td><?= $Confirm['nr_torrents'] ?></td>
        </tr>

        <tr>
            <td colspan="3"><input type="submit" value="Confirm" /></td>
        </tr>

    </table>

    <input type="hidden" name="curr" value="<?= $curr_id ?>" />
    <input type="hidden" name="prev" value="<?= $prev_id ?>" />
    <input type="hidden" name="check" value="<?= security_checksum($prev_id, $curr_id) ?>" />
    </form>
</div>
<?php } /* $Confirm */ ?>

<div class="box">
    <div class="head">Pair <?= SITE_NAME ?> user</div>

    <p>In the following section you will be asked to pair a user on <?= SITE_NAME ?> with their original account on the previous site.
    Once this assocation has been recorded, torrents, buffer, bookmarks etc, from the previous account will be assigned to
    the <?= SITE_NAME ?> account.</p>

    <div class="pad">
        <form method="post" action="/recovery.php?action=pair">
        <table>
            <tr>
                <th><?= SITE_NAME ?> ID</th>
                <td><input type="text" name="curr" width="10" value="<?= isset($curr_id) ? $curr_id : '' ?>" /></td>
            </tr>
            <tr>
                <th>Previous ID</th>
                <td><input type="text" name="prev" width="10" value="<?= isset($prev_id) ? $prev_id : '' ?>" /></td>
            </tr>
        </table>
        <input type="submit" value="Pair" />
        </form>
    </div>
</div>
<?php
View::show_footer();
