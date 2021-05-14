<?php
if (!check_perms('admin_recovery')) {
    error(403);
}
$recovery = new Gazelle\Recovery;

if (isset($_POST['username']) && strlen($_POST['username'])) {
    $class = 'username';
    $target = trim($_POST['username']);
    $List = $recovery->findByUsername($target);
}
elseif (isset($_POST['email']) && strlen($_POST['email'])) {
    $class = 'email';
    $target = trim($_POST['email']);
    $List = $recovery->findByEmail($target);
}
elseif (isset($_POST['announce']) && strlen($_POST['announce'])) {
    $class = 'announce';
    $target = trim($_POST['announce']);
    $List = $recovery->findByAnnounce($target);
}

View::show_header('Recovery browse users');
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

<?php if (isset($List)) { ?>
<div class="box pad">
<?php
    if (!count($List)) {
        echo "<p>No $class matched <b>$target</b></p>";
    }
    else {
?>
<div class="head">Users matched search criteria</div>
<p>The following <?= $class ?> match <b><?= $target ?></b> in the previous site</p>

<table>
<tr>
<th>Username</th>
<th>ID</th>
<th>Email</th>
<th>Uploaded</th>
<th>Downloaded</th>
<th>Enabled</th>
<th>Torrents</th>
<th>Announce</th>
</tr>

<?php foreach ($List as $r) { ?>
<tr>
<td><?= $r['Username'] ?></td>
<td><?= $r['UserID'] ?></td>
<td><?= $r['Email'] ?></td>
<td title="<?= $r['Uploaded'] ?>"><?= Format::get_size($r['Uploaded']) ?></td>
<td title="<?= $r['Downloaded'] ?>"><?= Format::get_size($r['Downloaded']) ?></td>
<td><?= $r['Enabled'] ?></td>
<td><?= $r['nr_torrents'] ?></td>
<td><?= $r['torrent_pass'] ?></td>
</tr>
<?php } /* foreach */ ?>
</table>
<?php } /* count() */ ?>
</div>
<?php } /* isset() */ ?>

<div class="box">
    <div class="head">Browse recovery details</div>

    <div class="pad">
        <form method="post" action="/recovery.php?action=browse">
        <p>Enter one of the following fields to search for members in the backup (Use <tt>%</tt> as a wildcard character, <i>e.g.</i> <tt>C17%</tt>).</p>
        <table>
            <tr>
                <th>Username</th>
                <td><input type="text" name="username" width="20" /></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><input type="text" name="email" width="20" /></td>
            </tr>
            <tr>
                <th>Announce</th>
                <td><input type="text" name="announce" width="20" /></td>
            </tr>
            <tr>
                <td></td>
                <td><input type="submit" value="Browse" /></td>
            </tr>
        </table>
        </form>
    </div>
</div>
<?php
View::show_footer();
