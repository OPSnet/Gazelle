<?php
if (!check_perms('admin_recovery')) {
    error(403);
}
$recovery = new Gazelle\Recovery;

if (isset($_GET['task'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        switch ($_GET['task']) {
            case 'accept';
                $ok = $recovery->accept($id, $LoggedUser['ID'], $LoggedUser['Username']);
                $message = $ok ? '<font color="#008000">Invite sent</font>' : '<font color="#800000">Invite not sent, check log</font>';
                break;
            case 'deny';
                $recovery->deny($id, $LoggedUser['ID'], $LoggedUser['Username']);
                $message = sprintf('<font color="orange">Request %d was denied</font>', $id);
                break;
            case 'unclaim';
                $recovery->unclaim($id, $LoggedUser['Username']);
                $message = sprintf('<font color="orange">Request %d was unclaimed</font>', $id);
                break;
            default:
                error(403);
                break;
        }
    }
} else {
    foreach (explode(' ', 'token username announce email') as $field) {
        if (array_key_exists($field, $_POST)) {
            $value = trim($_POST[$field]);
            if (strlen($value)) {
                header("Location: /recovery.php?action=search&$field=$value");
                exit;
            }
        }
    }
}


$Page = (isset($_GET['page']) && (int)$_GET['page'] > 0)
    ? (int)$_GET['page'] : 1;
$Limit  = 100;
$Offset = $Limit * ($Page-1);

$State = isset($_GET['state']) ? $_GET['state'] : 'pending';
$Total = $recovery->getTotal($State, $LoggedUser['ID']);
$Info  = $recovery->getList($Limit, $Offset, $State, $LoggedUser['ID']);

$Pages = Format::get_pages($Page, $Total, $Limit);

View::show_header('Recovery administration');
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

<form method="post" action="/recovery.php?action=admin">
<table>
<tr><th>Token</th><td><input type="text" width="30" name="token" /></td>
<th>Username</th><td><input type="text" width="30" name="username" /></td></tr>
<tr><th>Announce</th><td><input type="text" width="30" name="announce" /></td>
<th>Email</th><td><input type="text" width="30" name="email" /></td></tr>
<tr><td></td><td colspan="3"><input type="submit" value="Search" /></td></tr>
</table>

<h3><?= $Total ?> <?= $State ?> recovery requests</h3>

<?php if (isset($message)) { ?>
<h5><?= $message ?></h5>
<?php } ?>

<div class="linkbox">
    <?=$Pages?>
</div>

<div class="box">
    <div class="head">Registrations</div>
    <div class="pad">
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Token</th>
                <th>Email</th>
                <th>Announce</th>
                <th>Created</th>
                <th>Updated</th>
                <th>Action</th>
            </tr>
<?php foreach ($Info as $i) { ?>
            <tr>
                <td><?= $i['recovery_id'] ?></td>
                <td><?= $i['username'] ?></td>
                <td><tt><?= $i['token'] ?></tt></td>
                <td><?= $i['email'] ?></td>
                <td><?= $i['announce'] ?></td>
                <td><?= time_diff($i['created_dt']) ?></td>
                <td><?= time_diff($i['updated_dt']) ?></td>
                <td>
                    <a class="brackets" href="/recovery.php?action=view&amp;id=<?= $i['recovery_id'] ?>">View</a>
<?php   if ($i['state'] == 'PENDING') { ?>
                    <a class="brackets" href="/recovery.php?action=view&amp;id=<?= $i['recovery_id'] ?>&amp;claim=<?= $LoggedUser['ID'] ?>">Claim</a>
<?php   } ?>
                </td>
            </tr>
<?php } ?>
        </table>
    </div>
</div>

<div class="linkbox">
    <?=$Pages?>
</div>

</div>
<?php
View::show_footer();
