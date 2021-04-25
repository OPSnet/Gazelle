<?php
if (!check_perms('admin_recovery')) {
    error(403);
}
View::show_header('Recovery view user');

if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $ID = (int)$_GET['id'];
    $search = false;
}
elseif (isset($_GET['action']) && $_GET['action'] == 'search') {
    $search = true;
}
else {
    error(404);
}

if ($search) {
    $terms = [];
    foreach (explode(' ', 'token username email announce') as $key) {
        if (isset($_GET[$key])) {
            $terms[] = [$key => $_GET[$key]];
        }
    }
    $Info = \Gazelle\Recovery::search($terms, $DB);
    $ID = $Info['recovery_id'];
}
else {
    if (isset($_GET['claim']) and (int)$_GET['claim'] > 0) {
        $claim_id = (int)$_GET['claim'];
        if ($claim_id == G::$LoggedUser['ID']) {
            \Gazelle\Recovery::claim($ID, $claim_id, G::$LoggedUser['Username'], $DB);
        }
    }
    $Info = \Gazelle\Recovery::get_details($ID, $DB);
}

$Email = ($Info['email'] == $Info['email_clean'])
    ? $Info['email']
    : $Info['email_clean'] . "<br />(cleaned from " . $Info['email'] . ")";

$Candidate = \Gazelle\Recovery::get_candidate($Info['username'], $DB);
$enabled = ['Unconfirmed', 'Enabled', 'Disabled'];
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

<?php
if (!$Info) { ?>
<h3>Nobody home</h3>

<p>No recovery request matched the search terms<p>
<blockquote>
<ul>
<?php
    foreach ($terms as $t) {
        foreach ($t as $field => $value) {
            echo "<li>$field: <tt>$value</tt></li>";
        }
    }
?>
</ul>
</blockquote>

<?php
} else {
    $userMan = new Gazelle\Manager\User;
?>

<h3>View recovery details for <?= $Info['username'] ?></h3>

<div class="box">
    <div class="head">Registration details</div>
    <div class="pad">
        <table>
            <tr>
                <th>Username</th>
                <td><?= $Info['username'] ?></td>
                <td><?= count($Candidate) ? '<font color="#008000">Username matches (ID=' . $Candidate['UserID'] . ')</font>' : '<font color="#800000">No match for username</font>' ?></td>
                <th>state</th>
                <td><?= $Info['state'] ?></td>
            </tr>
<?php if (count($Candidate)) { ?>
            <tr>
                <td><?= $enabled[$Candidate['Enabled']] ?></td>
                <td><?= $userMan->userclassName($Candidate['PermissionID']) ?></th>
                <td><?= $Candidate['nr_torrents'] ?> torrents</td>
                <td><?= Format::get_size($Candidate['Uploaded']) ?> up</td>
                <td><?= Format::get_size($Candidate['Downloaded']) ?> down</td>
            </tr>
<?php } ?>
            <tr>
                <th>Password verified</th>
                <td colspan="2"><?= $Info['password_ok'] ? 'Yes' : 'No' ?></td>
                <th>Claimed by</th>
                <td><?= $Info['admin_user_id'] ? Users::format_username($Info['admin_user_id']) : 'nobody' ?></td>
            </tr>
            <tr>
                <th>email</th>
                <td><?= $Email ?></td>
                <td><?= $Candidate['Email'] ?></td>
                <th>Created</th>
                <td><?= time_diff($Info['created_dt']) ?></td>
            </tr>
            <tr>
                <th>Announce key</th>
                <td><?= $Info['announce'] ?></td>
                <td><?= $Candidate['torrent_pass'] ?></td>
                <th>Updated</th>
                <td><?= time_diff($Info['updated_dt']) ?></td>
            </tr>
            <tr>
                <th>Remote IP</th>
                <td><?= $Info['ipaddr'] ?></td>
                <td><?= str_replace(',', '<br />', $Candidate['ips']) ?></td>
                <th>Token</th>
                <td><tt><?= $Info['token'] ?></tt></td>
            </tr>
            <tr>
                <th>Screenshot info</th>
                <td colspan="4"><?= $Info['screenshot'] ?></td>
            </tr>
            <tr>
                <th>Invitation email</th>
                <td colspan="4"><pre><?= $Info['invite'] ?></pre></td>
            </tr>
            <tr>
                <th>info</th>
                <td colspan="4"><pre><?= $Info['info'] ?></pre></td>
            </tr>
            <tr>
                <th>log</th>
                <td colspan="4"><pre><?= $Info['log'] ?><pre></td>
            </tr>
        </table>
<?php
    if (in_array($Info['state'], ['PENDING', 'VALIDATED'])) { ?>
        <h2>Actions</h2>
<?php   if (in_array($Info['state'], ['PENDING', 'VALIDATED'])) { ?>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=accept&amp;id=<?= $ID ?>">Accept</a> - An invite will be emailed to the user</p>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=deny&amp;id=<?= $ID ?>">Deny</a> - The request is denied, no e-mail will be sent</p>
<?php
        }
        if ($Info['admin_user_id'] == G::$LoggedUser['ID']) {
?>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=unclaim&amp;id=<?= $ID ?>">Unclaim</a> - Release the claim on this request, you don't know what to do.</p>
<?php
        } else {
?>
        <p><a class="brackets" href="/recovery.php?action=view&amp;id=<?= $ID ?>&amp;claim=<?= G::$LoggedUser['ID'] ?>">Claim</a> - Claim this request, you need to contact the person via IRC.</p>
<?php
        }
    }
?>
    </div>
</div>

<?php
} /* $Info */ ?>
</div>
<?php
View::show_footer();
