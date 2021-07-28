<?php
if (!(check_perms('admin_rate_limit_view') || check_perms('admin_rate_limit_manage'))) {
    error(403);
}

$PRL = new \Gazelle\PermissionRateLimit;
if ($_POST) {
    authorize();
    if (isset($_POST['task'])) {
        $remove = array_filter($_POST, function ($x) { return preg_match('/^remove-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
        if (is_array($remove) && count($remove) == 1) {
            $PRL->remove(trim(array_keys($remove)[0], 'remove-'));
        } elseif ($_POST['task'] === 'add') {
            $val = new Gazelle\Util\Validator;
            $val->setFields([
                ['class', '1', 'number', 'class must be set'],
                ['factor', '1', 'number', 'factor must be set (usually, a number larger than 1.0)', ['minlength' => 1, 'allowperiod' => true]],
                ['overshoot', '1', 'number', 'overshoot must be set', ['minlength' => 1]],
            ]);
            if (!$val->validate($_POST)) {
                error($val->errorMessage());
            }
            $PRL->save($_POST['class'], $_POST['factor'], $_POST['overshoot']);
        } else {
            error(403);
        }
    }
}

View::show_header('Rate Limiting');
?>
<div class="header">
    <h2>Torrent Download Rate Limiting</h2>
</div>

<div class="box pad">
<p>This manager allows you to limit the number of torrent files the
various user classes are allowed to download without snatching. The factor is the ratio
of <code>downloaded / snatched</code>, where downloaded are files generated from [DL]
links, and snatched are torrents leeched completely. Once the factor is exceeded, only
<code>overshoot</code> further downloads are allowed per 24 hour rolling window.</p>

<p>If a user class is not defined in this page, no rate limiting applies to that
class.</p>

<?php if(check_perms('admin_rate_limit_manage')) { ?>
<p>You can whitelist a specific user by setting a flag in the User Information section
through the staff tools on their profile page.</p>
<?php } ?>
</div>

<form class="manage_form" name="accounts" action="/tools.php?action=rate_limit" method="post">
<table>
    <tr class="colhead">
        <td>Userclass</td>
        <td>Factor</td>
        <td>Overshoot</td>
<?php if (check_perms('admin_rate_limit_manage')) { ?>
        <td>Action</td>
<?php } ?>
    </tr>
<?php
$seen = [];
$row = 'b';
$list = $PRL->list();
foreach ($list as $rateLimitInfo) {
    $Row = $row === 'a' ? 'b' : 'a';
    $seen[$rateLimitInfo['ID']] = true;
?>
    <tr class="row<?=$row?>">
        <td><?= $rateLimitInfo['Name'] ?></td>
        <td><?= $rateLimitInfo['factor'] ?></td>
        <td><?= $rateLimitInfo['overshoot'] ?></td>
<?php if (check_perms('admin_rate_limit_manage')) { ?>
        <td>
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="submit" name="remove-<?= $rateLimitInfo['ID'] ?>" value="Remove"
                onclick="return confirm('Are you sure you want to remove this rate limit? This is an irreversible action!')" />
        </td>
<?php } ?>
    </tr>
<?php
}

if (check_perms('admin_rate_limit_manage')) {
    $perm = new \Gazelle\Permission;
    $permList = $perm->list();
?>
    <tr class="colhead">
        <td>Create</td>
        <td>Factor</td>
        <td>Overshoot</td>
        <td></td>
    </tr>
    <tr class="rowa">
        <td>
            <select name="class">
<?php
    foreach ($permList as $p) {
        if ($seen[$p['ID']] ?? true) {
            continue;
        }
?>
                    <option value="<?= $p['ID'] ?>"><?= $p['Name'] ?></option>
<?php } ?>
            </select>
        </td>
        <td><input type="text" size="6" name="factor" value="" placeholder="1.0" /></td>
        <td><input type="text" size="6" name="overshoot" /></td>
        <td>
            <input type="hidden" name="task" value="add" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="submit" name="submit" value="Create" />
        </td>
    </tr>
</table>
</form>
<?php
}
View::show_footer();
