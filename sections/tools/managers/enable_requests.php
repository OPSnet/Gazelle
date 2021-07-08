<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

if (!FEATURE_EMAIL_REENABLE) {
    // This feature is disabled
    header("Location: tools.php");
    die();
}

$Where = [];
$Joins = [];

// Build query for different views
if ($_GET['view'] == 'perfect') {
    $Where[] = "um.Email = uer.Email";
    $Joins[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
    $Where[] = "uer.IP = (SELECT IP FROM users_history_ips uhi1 WHERE uhi1.StartTime = (SELECT MAX(StartTime) FROM users_history_ips uhi2 WHERE uhi2.UserID = uer.UserID ORDER BY StartTime DESC LIMIT 1))";
    $Where[] = "(SELECT 1 FROM users_history_ips uhi WHERE uhi.IP = uer.IP AND uhi.UserID != uer.UserID) IS NULL";
    $Where[] = "ui.BanReason = '3'";
} else if ($_GET['view'] == 'minus_ip') {
    $Where[] = "um.Email = uer.Email";
    $Joins[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
    $Where[] = "ui.BanReason = '3'";
} else if ($_GET['view'] == 'invalid_email') {
    $Joins[] = "INNER JOIN users_main um ON (um.ID = uer.UserID)";
    $Where[] = "um.Email != uer.Email";
} else if ($_GET['view'] == 'ip_overlap') {
    $Joins[] = "INNER JOIN users_history_ips uhi ON (uhi.IP = uer.IP AND uhi.UserID != uer.UserID)";
} else if ($_GET['view'] == 'manual_disable') {
    $Where[] = "ui.BanReason != '3'";
} else {
    $Joins[] = '';
}
// End views
$args = [];

// Build query further based on search
if (isset($_GET['search'])) {
    $Username = trim($_GET['username']);
    $IP = trim($_GET['ip']);
    $SubmittedBetween = trim($_GET['submitted_between']);
    $SubmittedTimestamp1 = trim($_GET['submitted_timestamp1']);
    $SubmittedTimestamp2 = trim($_GET['submitted_timestamp2']);
    $HandledUsername = trim($_GET['handled_username']);
    $HandledBetween = trim($_GET['handled_between']);
    $HandledTimestamp1 = trim($_GET['handled_timestamp1']);
    $HandledTimestamp2 = trim($_GET['handled_timestamp2']);
    $OutcomeSearch = (int)$_GET['outcome_search'];
    $Checked = (isset($_GET['show_checked']));

    if (!empty($Username)) {
        $Joins[] = "INNER JOIN users_main um1 ON (um1.ID = uer.UserID)";
    }

    if (!empty($HandledUsername)) {
        $Joins[] = "INNER JOIN users_main um2 ON (um2.ID = uer.CheckedBy)";
    }

    [$cond, $args] = AutoEnable::build_search_query(
        $Username, $IP, $SubmittedBetween, $SubmittedTimestamp1, $SubmittedTimestamp2,
        $HandledUsername, $HandledBetween, $HandledTimestamp1, $HandledTimestamp2, $OutcomeSearch, $Checked
    );
    $Where = array_merge($Where, $cond);
}
// End search queries

$ShowChecked = $Checked || !empty($HandledUsername) || !empty($HandledTimestamp1) || !empty($OutcomeSearch);

if (!$ShowChecked || count($Where) == 0) {
    // If no search is entered, add this to the query to only show unchecked requests
    $Where[] = 'Outcome IS NULL';
}

// How can things be ordered?
$header = new \Gazelle\Util\SortableTableHeader('submitted_timestamp', [
    'submitted_timestamp' => ['dbColumn' => 'uer.Timestamp', 'defaultSort' => 'desc', 'text' => 'Age'],
    'handled_timestamp'   => ['dbColumn' => 'uer.Outcome',   'defaultSort' => 'desc', 'text' => ($ShowChecked) ? ' / Checked Date' : ''],
    'outcome'             => ['dbColumn' => 'uer.HandledTimestamp', 'defaultSort' => 'desc', 'text' => 'Outcome'],
]);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();
$joinList = implode(' ', $Joins);
$whereList = implode(' AND ', $Where);

$paginator = new Gazelle\Util\Paginator(ITEMS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($DB->scalar("
    SELECT count(*)
    FROM users_enable_requests AS uer
    INNER JOIN users_info ui ON (ui.UserID = uer.UserID)
    $joinList
    WHERE $whereList
    ", ...$args
));
array_push($args, $paginator->limit(), $paginator->offset());

$QueryID = $DB->prepared_query("
    SELECT uer.ID,
           uer.UserID,
           uer.Email,
           uer.IP,
           uer.UserAgent,
           uer.Timestamp,
           ui.BanReason,
           uer.CheckedBy,
           uer.HandledTimestamp,
           uer.Outcome
    FROM users_enable_requests AS uer
    INNER JOIN users_info ui ON (ui.UserID = uer.UserID)
    $joinList
    WHERE $whereList
    ORDER BY $OrderBy $OrderDir
    LIMIT ? OFFSET ?
    ", ...$args
);

View::show_header("Enable Requests", 'enable_requests');
?>
<div class="header">
    <h2>Auto-Enable Requests</h2>
</div>
<div align="center">
    <a class="brackets tooltip" href="?<?= Format::get_url(['view']) ?>" title="Default view">Main</a>
    <a class="brackets tooltip" href="?<?= Format::get_url([], true, false, ['view' => 'perfect']) ?>" title="Valid username, matching email, current IP with no matches, and inactivity disabled">Perfect</a>
    <a class="brackets tooltip" href="?<?= Format::get_url([], true, false, ['view' => 'minus_ip']) ?>" title="Valid username, matching email, and inactivity disabled">Perfect Minus IP</a>
    <a class="brackets tooltip" href="?<?= Format::get_url([], true, false, ['view' => 'invalid_email']) ?>" title="Non-matching email address">Invalid Email</a>
    <a class="brackets tooltip" href="?<?= Format::get_url([], true, false, ['view' => 'ip_overlap']) ?>" title="Requests with IP matches to other accounts">IP Overlap</a>
    <a class="brackets tooltip" href="?<?= Format::get_url([], true, false, ['view' => 'manual_disable']) ?>" title="Requests for accounts that were not disabled for inactivity">Manual Disable</a>
    <a class="brackets tooltip" href="" title="Show/Hide Search" onclick="$('#search_form').gtoggle(); return false;">Search</a>
    <a class="brackets tooltip" href="" title="Show/Hide Search" onclick="$('#scores').gtoggle(); return false;">Scores</a>
</div><br />
<div class="thin">
    <table id="scores" class="hidden" style="width: 50%; margin: 0 auto;">
        <tr>
            <th>Username</th>
            <th>Checked</th>
        </tr>
<?php
$DB->prepared_query("
    SELECT count(*), CheckedBy
    FROM users_enable_requests
    WHERE CheckedBy IS NOT NULL
    GROUP BY CheckedBy
    ORDER BY 1 DESC
");
while ([$Checked, $UserID] = $DB->next_record()) {
?>
            <tr>
                <td><?=Users::format_username($UserID)?></td>
                <td><?=$Checked?></td>
            </tr>
<?php
}
$DB->set_query_id($QueryID);
?>
    </table>
    <form action="" method="GET" id="search_form" <?=!isset($_GET['search']) ? 'class="hidden"' : ''?>>
        <input type="hidden" name="action" value="enable_requests" />
        <input type="hidden" name="view" value="<?=$_GET['view']?>" />
        <input type="hidden" name="search" value="1" />
        <table>
            <tr>
                <td class="label">Username</td>
                <td><input type="text" name="username" value="<?=$_GET['username']?>" /></td>
            </tr>
            <tr>
                <td class="label">IP Address</td>
                <td><input type="text" name="ip" value="<?=$_GET['ip']?>" /></td>
            </tr>
            <tr>
                <td class="label tooltip" title="This will search between the entered date and 24 hours after it">Submitted Timestamp</td>
                <td>
                    <select name="submitted_between" onchange="ChangeDateSearch(this.value, 'submitted_timestamp2');">
                        <option value="on" <?=$_GET['submitted_between'] == 'on' ? 'selected' : ''?>>On</option>
                        <option value="before" <?=$_GET['submitted_between'] == 'before' ? 'selected' : ''?>>Before</option>
                        <option value="after" <?=$_GET['submitted_between'] == 'after' ? 'selected' : ''?>>After</option>
                        <option value="between" <?=$_GET['submitted_between'] == 'between' ? 'selected' : ''?>>Between</option>
                    </select>&nbsp;
                    <input type="date" name="submitted_timestamp1" value="<?=$_GET['submitted_timestamp1']?>" />
                    <input type="date" id="submitted_timestamp2" name="submitted_timestamp2" value="<?=$_GET['submitted_timestamp2']?>" <?=$_GET['submitted_between'] != 'between' ? 'style="display: none;"' : ''?>/>
                </td>
            </tr>
            <tr>
                <td class="label">Handled By Username</td>
                <td><input type="text" name="handled_username" value="<?=$_GET['handled_username']?>" /></td>
            </tr>
            <tr>
                <td class="label tooltip" title="This will search between the entered date and 24 hours after it">Handled Timestamp</td>
                <td>
                    <select name="handled_between" onchange="ChangeDateSearch(this.value, 'handled_timestamp2');">
                        <option value="on" <?=$_GET['handled_between'] == 'on' ? 'selected' : ''?>>On</option>
                        <option value="before" <?=$_GET['handled_between'] == 'before' ? 'selected' : ''?>>Before</option>
                        <option value="after" <?=$_GET['handled_between'] == 'after' ? 'selected' : ''?>>After</option>
                        <option value="between" <?=$_GET['handled_between'] == 'between' ? 'selected' : ''?>>Between</option>
                    </select>&nbsp;
                <input type="date" name="handled_timestamp1" value="<?=$_GET['handled_timestamp1']?>" />
                <input type="date" id="handled_timestamp2" name="handled_timestamp2" value="<?=$_GET['handled_timestamp2']?>" <?=$_GET['handled_between'] != 'between' ? 'style="display: none;"' : ''?>/>
            </td>
            </tr>
            <tr>
                <td class="label">Outcome</td>
                <td>
                    <select name="outcome_search">
                        <option value="">---</option>
                        <option value="<?=AutoEnable::APPROVED?>" <?=$_GET['outcome_search'] == AutoEnable::APPROVED ? 'selected' : ''?>>Approved</option>
                        <option value="<?=AutoEnable::DENIED?>" <?=$_GET['outcome_search'] == AutoEnable::DENIED ? 'selected' : ''?>>Denied</option>
                        <option value="<?=AutoEnable::DISCARDED?>" <?=$_GET['outcome_search'] == AutoEnable::DISCARDED ? 'selected' : ''?>>Discarded</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Include Checked</td>
                <td><input type="checkbox" name="show_checked" <?=isset($_GET['show_checked']) ? 'checked' : ''?> /></td>
            </tr>
            <tr>
                <td class="label">Order By</td>
                <td>
                    <select name="order">
                        <option value="submitted_timestamp"<?php Format::selected('order', 'submitted_timestamp'); ?>>Submitted Timestamp</option>
                        <option value="outcome"<?php Format::selected('order', 'outcome'); ?>>Outcome</option>
                        <option value="handled_timestamp"<?php Format::selected('order', 'handled_timestamp'); ?>>Handled Timestamp</option>
                    </select>&nbsp;
                    <select name="sort">
                        <option value="desc"<?php Format::selected('sort', 'desc'); ?>>Descending</option>
                        <option value="asc"<?php Format::selected('sort', 'asc'); ?>>Ascending</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan=2><input type="submit" value="Search" /></td>
            </tr>
        </table>
    </form>
</div>
<?php if (!$paginator->total()) { ?>
    <h2 align="center">No new pending auto enable requests<?=isset($_GET['view']) ? ' in this view' : ''?></h2>
<?php } else { ?>
    <?= $paginator->linkbox() ?>
    <table width="100%">
        <tr class="colhead">
            <td class="center"><input type="checkbox" id="check_all" /></td>
            <td>Username</td>
            <td class="nobr">Email Address</td>
            <td class="nobr">IP Address</td>
            <td class="nobr">User Agent</td>
            <td class="nobr"><?= $header->emit('submitted_timestamp') ?></td>
            <td class="nobr">Ban Reason</td>
            <td class="nobr">Comment<?= $ShowChecked ? ' / Checked By' : ''?></td>
            <td class="nobr">Submit<?= $header->emit('handled_timestamp') ?></td>
<?php   if ($ShowChecked) { ?>
            <td><?= $header->emit('outcome') ?></td>
<?php   } ?>
        </tr>
    <?php
    $Row = 'a';
    while ([$ID, $UserID, $Email, $IP, $UserAgent, $Timestamp, $BanReason, $CheckedBy, $HandledTimestamp, $Outcome] = $DB->next_record()) {
        $Row = $Row === 'a' ? 'b' : 'a';
?>
        <tr class="row<?=$Row?>" id="row_<?=$ID?>">
            <td class="center">
<?php   if (!$HandledTimestamp) { ?>
                <input type="checkbox" id="multi" data-id="<?=$ID?>" />
<?php   } ?>
            </td>
            <td><?=Users::format_username($UserID)?></td>
            <td><?=display_str($Email)?></td>
            <td><?=display_str($IP)?></td>
            <td><?=display_str($UserAgent)?></td>
            <td><?=time_diff($Timestamp)?></td>
            <td><?=($BanReason == 3) ? '<b>Inactivity</b>' : 'Other'?></td>
<?php   if ($HandledTimestamp) { ?>
            <td><?=Users::format_username($CheckedBy);?></td>
            <td><?=$HandledTimestamp?></td>
<?php   } else { ?>
            <td><input class="inputtext" type="text" id="comment<?=$ID?>" placeholder="Comment" /></td>
            <td>
                <input type="submit" id="outcome" value="Approve" data-id="<?=$ID?>" />
                <input type="submit" id="outcome" value="Reject" data-id="<?=$ID?>" />
                <input type="submit" id="outcome" value="Discard" data-id="<?=$ID?>" />
            </td>
<?php
        }
        if ($ShowChecked) { ?>
            <td><?=AutoEnable::get_outcome_string($Outcome)?>
<?php       if ($Outcome == AutoEnable::DISCARDED) { ?>
                <a href="" id="unresolve" onclick="return false;" class="brackets" data-id="<?=$ID?>">Unresolve</a>
<?php       } ?>
            </td>
<?php   } ?>
        </tr>
<?php } ?>
    </table>
    <?= $paginator->linkbox() ?>
<div style="padding-bottom: 11px;">
    <input type="submit" id="outcome" value="Approve Selected" />
    <input type="submit" id="outcome" value="Reject Selected" />
    <input type="submit" id="outcome" value="Discard Selected" />
</div>
<?php
}
View::show_footer();
