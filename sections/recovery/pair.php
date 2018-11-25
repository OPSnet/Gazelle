<?
if (!check_perms('admin_recovery')) {
    error(403);
}

if (isset($_POST['curr']) && isset($_POST['prev'])) {
    $curr_id = (int)trim($_POST['curr']);
    $found = \Users::exists($curr_id);
    if (!$found) {
        $Result = "No current ID found for <tt>$curr_id</tt>.";
    }
    else {
        $curr = \Users::user_info($curr_id);
        $prev_id = (int)trim($_POST['prev']);
        $prev = \Gazelle\Recovery::get_candidate_by_id($prev_id, G::$DB);
        if (!$prev) {
            $Result = "No previous ID found for <tt>$prev_id</tt>.";
        }
        elseif ($Map = \Gazelle\Recovery::is_mapped($prev_id, G::$DB)) {
            $ID = $Map[0]['ID'];
            $Result = "Previous id $prev_id already mapped to " . \Users::format_username($ID);
        }
        elseif ($Map = \Gazelle\Recovery::is_mapped_local($curr_id, G::$DB)) {
            $ID = $Map[0]['ID'];
            $Result = \Users::format_username($curr_id) . " is already mapped to previous id $ID";
        }
        else {
            $Result = \Gazelle\Recovery::map_to_previous($curr_id, $prev_id, G::$LoggedUser['Username'], G::$DB)
                ? \Users::format_username($curr_id) . " has been successfully mapped to previous id $prev_id"
                : "DB Error: did not map $curr_id to $prev_id";
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

<? if (isset($Result)) { ?>
<div class="box">
    <div class="head">Result</div>

    <div><?= $Result ?></div>
</div>
<? } ?>

<div class="box">
    <div class="head">Pair Orpheus user</div>

    <p>In the following section you will be asked to pair an Orpheus user with their original account on the previous site.
    Once this assocation has been recorded, torrents, buffer, bookmarks etc, from the previous account will be assigned to
    the Orpheus account.</p>

    <div class="pad">
        <form method="post" action="/recovery.php?action=pair">
        <table>
            <tr>
                <th>Orpheus ID</th>
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
