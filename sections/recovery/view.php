<?
if (!check_perms('admin_recovery')) {
	error(403);
}
View::show_header('Recovery view user');

if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $ID = (int)$_GET['id'];
}
else {
    error(404);
}

if (isset($_GET['claim']) and (int)$_GET['claim'] > 0) {
    $claim_id = (int)$_GET['claim'];
    if ($claim_id = G::$LoggedUser['ID']) {
        \Gazelle\Recovery::claim($ID, $claim_id, G::$LoggedUser['Username'], G::$DB);
    }
}
$Info  = \Gazelle\Recovery::get_details($ID, G::$DB);
$Email = ($Info['email'] == $Info['email_clean'])
    ? $Info['email']
    : $Info['email_clean'] . "<br />(cleaned from " . $Info['email'] . ")";

$Candidate = \Gazelle\Recovery::get_candidate($Info['username'], G::$DB);
?>

<div class="thin">

<div class="linkbox">
	<a class="brackets" href="/recovery.php?action=admin&amp;state=pending">Pending</a>
	<a class="brackets" href="/recovery.php?action=admin&amp;state=validated">Validated</a>
	<a class="brackets" href="/recovery.php?action=admin&amp;state=accepted">Accepted</a>
	<a class="brackets" href="/recovery.php?action=admin&amp;state=denied">Denied</a>
	<a class="brackets" href="/recovery.php?action=admin&amp;state=claimed">Your claimed</a>
</div>

<h3>View recovery details for <?= $Info['username'] ?></h3>

<div class="box">
	<div class="head">Registration details</div>
	<div class="pad">
        <table>
            <tr>
                <th>Username</th>
                <td><?= $Info['username'] ?></td>
                <td><?= count($Candidate) ? '<font color="#008000">Username matches</font>' : '<font color="#800000">No match for username</font>' ?></td>
                <th>state</th>
                <td><?= $Info['state'] ?></td>
            </tr>
            <tr>
                <th>Password verified</th>
                <td colspan="2"><?= $Info['password_ok'] = 1 ? 'Yes' : 'No' ?></td>
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

        <h2>Actions</h2>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=accept&amp;id=<?= $ID ?>">Accept</a> - An invite will be emailed to the user</p>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=deny&amp;id=<?= $ID ?>">Deny</a> - The request is denied, no e-mail will be sent</p>
        <p><a class="brackets" href="/recovery.php?action=admin&amp;task=unclaim&amp;id=<?= $ID ?>">Unclaim</a> - Release the claim on this request, you don't know what to do.</p>
	</div>
</div>

</div>
<?
View::show_footer();


