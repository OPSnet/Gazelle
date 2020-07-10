<?php

if (isset($_GET['search'])) {
    $_GET['search'] = trim($_GET['search']);
}

if (!empty($_GET['search'])) {
    if (preg_match('/^'.IP_REGEX.'$/', $_GET['search'])) {
        $_GET['ip'] = $_GET['search'];
    } elseif (preg_match('/^'.EMAIL_REGEX.'$/i', $_GET['search'])) {
        $_GET['email'] = $_GET['search'];
    } elseif (preg_match(USERNAME_REGEX,$_GET['search'])) {
        $DB->prepared_query('
            SELECT ID
            FROM users_main
            WHERE Username = ?
            ', $_GET['search']
        );
        if (list($ID) = $DB->next_record()) {
            header("Location: user.php?id=$ID");
            die();
        }
        $_GET['username'] = $_GET['search'];
    } else {
        $_GET['comment'] = $_GET['search'];
    }
}

foreach (['ip', 'email', 'username', 'comment'] as $field) {
    if (isset($_GET[$field])) {
        $_GET[$field] = trim($_GET[$field]);
    }
}

define('USERS_PER_PAGE', 50);

class SQLMatcher {
    protected $key;

    public function __construct($key) {
        $this->key = $key;
    }

    public function match($field) {
        switch ($this->key) {
            case 'regexp':
                return "$field REGEXP ?";
            case 'strict':
                return "$field = ?";
            case 'fuzzy':
            default:
                return "$field LIKE concat('%', ?, '%')";
        }
    }

    public function left_match($field) {
        switch ($this->key) {
            case 'regexp':
                return "$field REGEXP ?";
            case 'strict':
                return "$field = ?";
            case 'fuzzy':
            default:
                return "$field LIKE concat(?, '%')";
        }
    }

    public function op($field, $compare) {
        switch ($compare) {
            case 'above':
                return "$field > ?";
            case 'below':
                return "$field < ?";
            case 'isnull':
                return "$field IS NULL";
            case 'isnotnull':
                return "$field IS NOT NULL";
            case 'no':
            case 'not_equal':
                return "$field != ?";
            case 'between':
                return "$field BETWEEN ? AND ?";
            case 'yes':
            case 'equal':
            default:
                return "$field = ?";
        }
    }

    public function date($field, $compare) {
        switch ($compare) {
            case 'before':
                return "$field < ?";
            case 'after':
                return "$field > ? + INTERVAL 1 DAY";
            case 'between':
                return "$field BETWEEN ? AND ? + INTERVAL 1 DAY";
            case 'on':
            default:
                return "$field >= ? AND $field < ? + INTERVAL 1 DAY";
        }
    }
}

function option($field, $value, $label) {
    return sprintf('<option value="%s"%s>%s</option>',
        $value,
        $_GET[$field] === $value ? ' selected="selected"' : '',
        $label);
}

$OrderTable = [
    'Bounty'     => 'Bounty',
    'Downloaded' => 'uls1.Downloaded',
    'Downloads'  => 'Downloads',
    'Email'      => 'um1.Email',
    'Invites'    => 'um1.Invites',
    'Joined'     => 'ui1.JoinDate',
    'Last Seen'  => 'ula.last_access',
    'Ratio'      => '(um1.Uploaded / um1.Downloaded)',
    'Seeding'    => 'Seeding',
    'Snatches'   => 'Snatches',
    'Uploaded'   => 'uls1.Uploaded',
    'Username'   => 'um1.Username',
];
$WayTable = ['Ascending'=>'ASC', 'Descending'=>'DESC'];

// Arrays, regexps, and all that fun stuff we can use for validation, form generation, etc
$OrderVals = ['inarray'=>array_keys($OrderTable)];
$WayVals = ['inarray'=>array_keys($WayTable)];

$DateChoices = ['inarray'=>['on', 'before', 'after', 'between']];
$SingleDateChoices = ['inarray'=>['on', 'before', 'after']];
$NumberChoices = ['inarray'=>['equal', 'above', 'below', 'between', 'buffer']];
$OffNumberChoices = ['inarray'=>['equal', 'above', 'below', 'between', 'buffer', 'off']];
$YesNo = ['inarray'=>['any', 'yes', 'no']];
$Nullable = ['inarray'=>['any', 'isnull', 'isnotnull']];

$emailHistoryChecked = false;
$ipHistoryChecked = false;
$disabledIpChecked = false;
$trackerLiveSource = true;

if (count($_GET)) {
    $emailHistoryChecked = !empty($_GET['email_history']);
    $disabledIpChecked = !empty($_GET['disabled_ip']);
    $ipHistoryChecked = !empty($_GET['ip_history']);
    $trackerLiveSource = ($_GET['tracker-src'] ?? 'live') == 'live';
    $DateRegexp = ['regexp' => '/\d{4}-\d{2}-\d{2}/'];
    $ClassIDs = [];
    $SecClassIDs = [];
    foreach ($Classes as $ClassID => $Value) {
        if ($Value['Secondary']) {
            $SecClassIDs[] = $ClassID;
        } else {
            $ClassIDs[] = $ClassID;
        }
    }
    $StylesheetsManager = new \Gazelle\Stylesheet;
    $Stylesheets = $StylesheetsManager->list();

    $Val->SetFields('avatar', '0', 'string', 'Avatar URL too long', ['maxlength' => 512]);
    $Val->SetFields('bounty', '0', 'inarray', "Invalid bounty field", $OffNumberChoices);
    $Val->SetFields('cc', '0', 'inarray', 'Invalid Country Code', ['maxlength' => 2]);
    // $Val->SetFields('class', '0', 'inarray', 'Invalid class', ['inarray' => $ClassIDs]);
    $Val->SetFields('comment', '0', 'string', 'Comment is too long.', ['maxlength' => 512]);
    $Val->SetFields('disabled_invites', '0', 'inarray', 'Invalid disabled_invites field', $YesNo);
    $Val->SetFields('disabled_uploads', '0', 'inarray', 'Invalid disabled_uploads field', $YesNo);
    $Val->SetFields('donor', '0', 'inarray', 'Invalid donor field', $YesNo);
    $Val->SetFields('downloaded', '0', 'inarray', 'Invalid downloaded field', $NumberChoices);
    $Val->SetFields('enabled', '0', 'inarray', 'Invalid enabled field', ['inarray' => ['', 0, 1, 2]]);
    $Val->SetFields('join1', '0', 'regexp', 'Invalid join1 field', $DateRegexp);
    $Val->SetFields('join2', '0', 'regexp', 'Invalid join2 field', $DateRegexp);
    $Val->SetFields('joined', '0', 'inarray', 'Invalid joined field', $DateChoices);
    $Val->SetFields('lastactive', '0', 'inarray', 'Invalid lastactive field', $DateChoices);
    $Val->SetFields('lastactive1', '0', 'regexp', 'Invalid lastactive1 field', $DateRegexp);
    $Val->SetFields('lastactive2', '0', 'regexp', 'Invalid lastactive2 field', $DateRegexp);
    $Val->SetFields('lockedaccount', '0', 'inarray', 'Invalid locked account field', ['inarray' => ['any', 'locked', 'unlocked']]);
    $Val->SetFields('matchtype', '0', 'inarray', 'Invalid matchtype field', ['inarray' => ['strict', 'fuzzy', 'regexp']]);
    $Val->SetFields('order', '0', 'inarray', 'Invalid ordering', $OrderVals);
    $Val->SetFields('passkey', '0', 'string', 'Invalid passkey', ['maxlength' => 32]);
    $Val->SetFields('ratio', '0', 'inarray', 'Invalid ratio field', $NumberChoices);
    $Val->SetFields('secclass', '0', 'inarray', 'Invalid class', ['inarray' => $SecClassIDs]);
    $Val->SetFields('seeding', '0', 'inarray', "Invalid seeding field", $OffNumberChoices);
    $Val->SetFields('snatched', '0', 'inarray', "Invalid snatched field", $OffNumberChoices);
    $Val->SetFields('stylesheet', '0', 'inarray', 'Invalid stylesheet', array_unique(array_keys($Stylesheets)));
    $Val->SetFields('uploaded', '0', 'inarray', 'Invalid uploaded field', $NumberChoices);
    $Val->SetFields('warned', '0', 'inarray', 'Invalid warned field', $Nullable);
    $Val->SetFields('way', '0', 'inarray', 'Invalid way', $WayVals);

    $Err = $Val->ValidateForm($_GET);

    if (!$Err) {
        // Passed validation. Let's rock.
        $m = new SQLMatcher($_GET['matchtype']);

        $Where = [];
        $Args = [];
        $Having = [];
        $HavingArgs = [];
        $Join = [];
        $Distinct = false;
        $Order = '';

        $inviteesValue = $_GET['invitees'] == 'off'
            ? "'X'"
            : '(SELECT count(*) FROM users_info AS ui2 WHERE ui2.Inviter = um1.ID)';
        $seedingValue = $_GET['seeding'] == 'off'
            ? "'X'"
            : '(SELECT count(DISTINCT fid)
                FROM xbt_files_users xfu
                WHERE xfu.active = 1 AND xfu.remaining = 0 AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                    AND xfu.uid = um1.ID)';
        $snatchesValue = $_GET['snatched'] == 'off'
            ? "'X'"
            : '(SELECT count(DISTINCT fid) FROM xbt_snatched AS xs WHERE xs.uid = um1.ID)';

        $SQL = "
            SQL_CALC_FOUND_ROWS
            um1.ID,
            um1.Username,
            uls1.Uploaded,
            uls1.Downloaded,
            (SELECT count(DISTINCT TorrentID) FROM users_downloads ud WHERE ud.UserID = um1.ID) as Downloads,
            coalesce((SELECT sum(Bounty) FROM requests_votes rv WHERE rv.UserID = um1.ID), 0) as Bounty,
            $seedingValue AS Seeding,
            $snatchesValue AS Snatches,
            $inviteesValue AS Invitees,
            um1.PermissionID,
            um1.Email,
            um1.Enabled,
            um1.Invites,
            ui1.DisableInvites,
            ui1.Warned,
            ui1.Donor,
            ui1.JoinDate,
            ula.last_access
        FROM users_main AS um1
        INNER JOIN users_leech_stats AS uls1 ON (uls1.UserID = um1.ID)
        INNER JOIN users_info AS ui1 ON (ui1.UserID = um1.ID)
        LEFT JOIN user_last_access AS ula ON (ula.user_id = um1.ID)
        ";

        if (!empty($_GET['username'])) {
            $Where[] = $m->match('um1.Username');
            $Args[] = $_GET['username'];
        }

        if (!empty($_GET['email'])) {
            if (isset($_GET['email_history'])) {
                $Distinct = true;
                $Join['he'] = 'INNER JOIN users_history_emails AS he ON (he.UserID = um1.ID)';
                $Where[] = $m->match('he.Email');
            } else {
                $Where[] = $m->match('um1.Email');
            }
            $Args[] = $_GET['email'];
        }

        if (!empty($_GET['email_cnt']) && is_number($_GET['email_cnt'])) {
            $Where[] = sprintf('um1.ID IN (%s)',
                $m->op("
                    SELECT UserID FROM users_history_emails GROUP BY UserID HAVING count(DISTINCT Email)
                    ", $_GET['emails_opt']
                )
            );
            $Args[] = trim($_GET['email_cnt']);
        }

        if (!empty($_GET['ip'])) {
            if ($ipHistoryChecked) {
                $Distinct = true;
                $Join['hi'] = 'INNER JOIN users_history_ips AS hi ON (hi.UserID = um1.ID)';
                $Where[] = $m->left_match('hi.IP');
            } else {
                $Where[] = $m->left_match('um1.IP');
            }
            $Args[] = trim($_GET['ip']);
        }

        if ($_GET['lockedaccount'] == 'locked') {
            $Join['la'] .= 'INNER JOIN locked_accounts AS la ON (la.UserID = um1.ID)';
        }
        elseif ($_GET['lockedaccount'] == 'unlocked') {
            $Join['la'] = 'LEFT JOIN locked_accounts AS la ON (la.UserID = um1.ID)';
            $Where[] = 'la.UserID IS NULL';
        }

        if (!empty($_GET['cc'])) {
            $Where[] = $m->op('um1.ipcc', $_GET['cc_op']);
            $Args[] = trim($_GET['cc']);
        }

        if (!empty($_GET['tracker_ip'])) {
            $Distinct = true;
            $Join['xfu'] = $trackerLiveSource
                ? 'INNER JOIN xbt_files_users AS xfu ON (um1.ID = xfu.uid)'
                : 'INNER JOIN xbt_snatched AS xfu ON (um1.ID = xfu.uid)';
            $Where[] = $m->left_match('xfu.ip');
            $Args[] = trim($_GET['tracker_ip']);
        }

        if (!empty($_GET['comment'])) {
            $Where[] = $m->match('ui1.AdminComment');
            $Args[] = $_GET['comment'];
        }

        if (!empty($_GET['lastfm'])) {
            $Distinct = true;
            $Join['lfm'] = 'INNER JOIN lastfm_users AS lfm ON (lfm.ID = um1.ID)';
            $Where[] = $m->match('lfm.Username');
            $Args[] = $_GET['lastfm'];
        }

        if (strlen($_GET['invites1'])) {
            $op = $_GET['invites'];
            $Where[] = $m->op('um1.Invites', $op);
            $Args = array_merge($Args, [$_GET['invites1']], ($op === 'between' ? [$_GET['invites2']] : []));
        }

        if (strlen($_GET['invitees1']) && $_GET['invitees'] !== 'off') {
            $op = $_GET['invitees'];
            $Having[] = $m->op('Invitees', $op);
            $HavingArgs = array_merge($HavingArgs, [$_GET['invitees1']], ($op === 'between' ? [$_GET['invitees2']] : []));
        }

        if ($_GET['disabled_invites']) {
            $Where[] = 'ui1.DisableInvites = ?';
            $Args[] = $_GET['disabled_invites'] === 'yes' ? '1' : '0';
        }

        if ($_GET['disabled_uploads']) {
            $Where[] = 'ui1.DisableUpload = ?';
            $Args[] = $_GET['disabled_uploads'] === 'yes' ? '1' : '0';
        }

        if ($_GET['join1']) {
            $op = $_GET['joined'];
            $Where[] = $m->date('ui1.JoinDate', $op);
            $Args[] = $_GET['join1'];
            if ($op === 'on') {
                $Args[] = $_GET['join1'];
            }
            elseif ($op === 'between') {
                $Args[] = $_GET['join2'];
            }
        }

        if ($_GET['lastactive1']) {
            $op = $_GET['lastactive'];
            $Where[] = $m->date('ula.last_access', $op);
            $Args[] = $_GET['lastactive1'];
            if ($op === 'on') {
                $Args[] = $_GET['lastactive1'];
            }
            elseif ($op === 'between') {
                $Args[] = $_GET['lastactive2'];
            }
        }

        if (strlen($_GET['ratio1'])) {
            $Decimals = strlen(array_pop(explode('.', $_GET['ratio1'])));
            if (!$Decimals) {
                $Decimals = 0;
            }
            $op = $_GET['ratio'];
            $Where[] = $m->op('CASE WHEN uls1.Downloaded = 0 then 0 ELSE round(uls1.Uploaded/uls1.Downloaded, ?) END', $op);
            $Args = array_merge($Args, [$Decimals, $_GET['ratio1']], ($op === 'between' ? [$_GET['ratio2']] : []));
        }

        if ($_GET['bounty'] !== 'off' && strlen($_GET['bounty1'])) {
            $op = $_GET['bounty'];
            $Having[] = $m->op('Bounty', $op);
            $HavingArgs = array_merge($HavingArgs, [$_GET['bounty1'] * 1024 ** 3], ($op === 'between' ? [$_GET['bounty2'] * 1024 ** 3] : []));
        }

        if ($_GET['downloads'] !== 'off' && strlen($_GET['downloads1'])) {
            $op = $_GET['downloads'];
            $Having[] = $m->op('Downloads', $op);
            $HavingArgs = array_merge($HavingArgs, [$_GET['downloads1']], ($op === 'between' ? [$_GET['downloads2']] : []));
        }

        if ($_GET['seeding'] !== 'off' && strlen($_GET['seeding1'])) {
            $op = $_GET['seeding'];
            $Having[] = $m->op('Seeding', $op);
            $HavingArgs = array_merge($HavingArgs, [$_GET['seeding1']], ($op === 'between' ? [$_GET['seeding2']] : []));
        }

        if ($_GET['snatched'] !== 'off' && strlen($_GET['snatched1'])) {
            $op = $_GET['snatched'];
            $Having[] = $m->op('Snatches', $op);
            $HavingArgs = array_merge($HavingArgs, [$_GET['snatched1']], ($op === 'between' ? [$_GET['snatched2']] : []));
        }

        if (strlen($_GET['uploaded1'])) {
            $op = $_GET['uploaded'];
            if ($op === 'buffer') {
                $Where[] = 'uls1.Uploaded - uls1.Downloaded BETWEEN ? AND ?';
                $Args = array_merge($Args, [0.9 * $_GET['uploaded1'] * 1024 ** 3, 1.1 * $_GET['uploaded1'] * 1024 ** 3]);
            } else {
                $Where[] = $m->op('uls1.Uploaded', $op);
                $Args[] = $_GET['uploaded1'] * 1024 ** 3;
                if ($op === 'on') {
                    $Args[] = $_GET['uploaded1'] * 1024 ** 3;
                }
                elseif ($op === 'between') {
                    $Args[] = $_GET['uploaded2'] * 1024 ** 3;
                }
            }
        }

        if (strlen($_GET['downloaded1'])) {
            $op = $_GET['downloaded'];
            $Where[] = $m->op('uls1.Downloaded', $op);
            $Args[] = $_GET['downloaded1'] * 1024 ** 3;
            if ($op === 'on') {
                $Args[] = $_GET['downloaded1'] * 1024 ** 3;
            }
            elseif ($op === 'between') {
                $Args[] = $_GET['downloaded2'] * 1024 ** 3;
            }
        }

        if ($_GET['enabled'] != '') {
            $Where[] = 'um1.Enabled = ?';
            $Args[] = $_GET['enabled'];
        }

        if (isset($_GET['class']) && is_array($_GET['class'])) {
            $Where[] = 'um1.PermissionID IN (' . placeholders($_GET['class']) . ')';
            $Args = array_merge($Args, $_GET['class']);
        }

        if ($_GET['secclass'] != '') {
            $Join['ul'] = 'INNER JOIN users_levels AS ul ON (um1.ID = ul.UserID)';
            $Where[] = 'ul.PermissionID = ?';
            $Args[] = $_GET['secclass'];
        }

        if ($_GET['donor']) {
            $Where[] = 'ui1.Donor = ?';
            $Args[] = $_GET['donor'] === 'yes' ? '1' : '0';
        }

        if ($_GET['warned']) {
            $Where[] = $m->op('ui1.Warned', $_GET['warned']);
        }

        if ($disabledIpChecked) {
            $Distinct = true;
            if ($ipHistoryChecked) {
                if (!isset($Join['hi'])) {
                    $Join['hi'] = 'LEFT JOIN users_history_ips AS hi ON (hi.UserID = um1.ID)';
                }
                $Join['um2'] = 'LEFT JOIN users_main AS um2 ON (um2.ID != um1.ID AND um2.Enabled = \'2\' AND um2.ID = hi.UserID)';
            } else {
                $Join['um2'] = 'LEFT JOIN users_main AS um2 ON (um2.ID != um1.ID AND um2.Enabled = \'2\' AND um2.IP = um1.IP)';
            }
        }

        if (!empty($_GET['passkey'])) {
            $Where[] = $m->match('um1.torrent_pass');
            $Args[] = $_GET['passkey'];
        }

        if (!empty($_GET['avatar'])) {
            $Where[] = $m->match('ui1.Avatar');
            $Args[] = $_GET['avatar'];
        }

        if (!empty($_GET['stylesheet'])) {
            $Where[] = $m->match('ui1.StyleID');
            $Args[] = $_GET['stylesheet'];
        }

        if ($OrderTable[$_GET['order']] && $WayTable[$_GET['way']]) {
            $Order = 'ORDER BY '.$OrderTable[$_GET['order']].' '.$WayTable[$_GET['way']];
        }

        //---------- Build the query
        $SQL = 'SELECT ' . $SQL . implode("\n", $Join);

        if (count($Where)) {
            $SQL .= "\nWHERE " . implode("\nAND ", $Where);
        }

        if ($Distinct) {
            $SQL .= "\nGROUP BY um1.ID";
        }

        if (count($Having)) {
            $SQL .= "\nHAVING " . implode(' AND ', $Having);
        }

        list($Page, $Limit) = Format::page_limit(USERS_PER_PAGE);
        $SQL .= "\n$Order LIMIT $Limit";
    } else {
        error($Err);
    }
}
View::show_header('User search');
?>

<div class="thin">
    <form class="search_form" name="users" action="user.php" method="get">
        <input type="hidden" name="action" value="search" />
        <table class="layout">
        <tr>
            <!-- col1 -->
            <td style="vertical-align:top;"><table class="layout">
                <tr>
                <td class="label nobr">Username:</td>
                <td>
                    <input type="text" name="username" size="20" value="<?=display_str($_GET['username'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Email address:</td>
                <td>
                    <input type="text" name="email" size="20" value="<?=display_str($_GET['email'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="To fuzzy search (default) for a block of addresses (e.g. 55.66.77.*), enter &quot;55.66.77.&quot; without the quotes">Site IP:</td>
                <td>
                    <input type="text" name="ip" size="20" value="<?=display_str($_GET['ip'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Tracker IP:<br />
                  <div style="padding-left: 20px; text-align: left;">
                    <input type="radio" name="tracker-src" id="tracker-src-live" value="live"<?= $trackerLiveSource ? ' checked="checked"' : '' ?> />
                    <label class="tooltip" for="tracker-src" title="Search for client ip addresses currently connecting to the tracker" for="tracker-src-live">Live</label><br />
                    <input type="radio" name="tracker-src" id="tracker-src-hist" value="hist"<?= !$trackerLiveSource ? ' checked="checked"' : '' ?> />
                    <label class="tooltip" for="tracker-src" title="Search for ip addresses that have been seen by the tracker (but may be not connected at this time)" for="tracker-src-hist">Historical</label>
                  </div>
                </td>
                <td>
                    <input type="text" name="tracker_ip" size="20" value="<?=display_str($_GET['tracker_ip'])?>" />
                </td>
                </tr>

                <tr>
<?php if (check_perms('users_mod')) { ?>
                <td class="label nobr">Staff notes:</td>
                <td>
                    <input type="text" name="comment" size="20" value="<?=display_str($_GET['comment'])?>" />
                </td>
<?php } else { ?>
                <td class="label nobr"></td>
                <td>
                </td>
<?php } ?>
                </tr>

                <tr>
                <td class="label nobr">Passkey:</td>
                <td>
                    <input type="text" name="passkey" size="20" value="<?=display_str($_GET['passkey'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="Supports partial URL matching, e.g. entering &quot;&#124;https://ptpimg.me&quot; will search for avatars hosted on https://phpimg.me">Avatar URL:</td>
                <td>
                    <input type="text" name="avatar" size="20" value="<?=display_str($_GET['avatar'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Last.fm username:</td>
                <td>
                    <input type="text" name="lastfm" size="20" value="<?=display_str($_GET['lastfm'])?>" />
                </td>
                </tr>

                <tr>
                <td class="nobr" colspan="2">
                <h4>Extra</h4>
                <ul class="options_list nobullet">
                    <li title="Only display users that have a disabled account linked by IP address">
                        <input type="checkbox" name="disabled_ip" id="disabled_ip"<?= $disabledIpChecked ?' checked="checked"' : '' ?> />
                        <label for="disabled_ip">Disabled accounts linked by IP</label>
                    </li>
                    <li>
                        <input type="checkbox" name="ip_history" id="ip_history"<?= $ipHistoryChecked ? ' checked="checked"' : '' ?> />
                        <label title="Disabled accounts linked by IP must also be checked" for="ip_history">IP history</label>
                    </li>
                    <li>
                        <input type="checkbox" name="email_history" id="email_history"<?= $emailHistoryChecked ? ' checked="checked"' : '' ?> />
                        <label title="Also search the email addresses the member used in the past" for="email_history">Email history</label>
                    </li>
                </ul>
                </tr>

            </table></td>
            <!-- col2 -->
            <td style="vertical-align:top;"><table class="layout">

                <tr>
                <td class="label nobr">Joined:</td>
                <td style="white-space: nowrap;">
                    <select name="joined">
                        <?= option('joined', 'on', 'On') ?>
                        <?= option('joined', 'before', 'Before') ?>
                        <?= option('joined', 'after', 'After') ?>
                        <?= option('joined', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="join1" size="10" value="<?=display_str($_GET['join1'])?>" placeholder="YYYY-MM-DD" />
                    <input type="text" name="join2" size="10" value="<?=display_str($_GET['join2'])?>" placeholder="YYYY-MM-DD" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Last active:</td>
                <td style="white-space: nowrap;">
                    <select name="lastactive">
                        <?= option('lastactive', 'on', 'On') ?>
                        <?= option('lastactive', 'before', 'Before') ?>
                        <?= option('lastactive', 'after', 'After') ?>
                        <?= option('lastactive', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="lastactive1" size="10" value="<?=display_str($_GET['lastactive1'])?>" placeholder="YYYY-MM-DD" />
                    <input type="text" name="lastactive2" size="10" value="<?=display_str($_GET['lastactive2'])?>" placeholder="YYYY-MM-DD" />
                </td>
                </tr>

                <tr>
                <td class="label nobr" title="The number of releases downloaded (may be greater than snatched">Downloads:</td>
                <td width="30%">
                    <select name="downloads">
                        <option value="equal"<?php   if (isset($_GET['downloads']) && $_GET['downloads'] === 'equal')   { echo ' selected="selected"'; } ?>>Equal</option>
                        <option value="above"<?php   if (isset($_GET['downloads']) && $_GET['downloads'] === 'above')   { echo ' selected="selected"'; } ?>>Above</option>
                        <option value="below"<?php   if (isset($_GET['downloads']) && $_GET['downloads'] === 'below')   { echo ' selected="selected"'; } ?>>Below</option>
                        <option value="between"<?php if (isset($_GET['downloads']) && $_GET['downloads'] === 'between') { echo ' selected="selected"'; } ?>>Between</option>
                        <option value="off"<?php     if (!isset($_GET['downloads']) || $_GET['downloads'] === 'off')     { echo ' selected="selected"'; } ?>>Off</option>
                    </select>
                    <input type="text" name="downloads1" size="6" value="<?=display_str($_GET['downloads1'])?>" />
                    <input type="text" name="downloads2" size="6" value="<?=display_str($_GET['downloads2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Snatched:</td>
                <td width="30%">
                    <select name="snatched">
                        <option value="equal"<?php   if (isset($_GET['snatched']) && $_GET['snatched'] === 'equal')   { echo ' selected="selected"'; } ?>>Equal</option>
                        <option value="above"<?php   if (isset($_GET['snatched']) && $_GET['snatched'] === 'above')   { echo ' selected="selected"'; } ?>>Above</option>
                        <option value="below"<?php   if (isset($_GET['snatched']) && $_GET['snatched'] === 'below')   { echo ' selected="selected"'; } ?>>Below</option>
                        <option value="between"<?php if (isset($_GET['snatched']) && $_GET['snatched'] === 'between') { echo ' selected="selected"'; } ?>>Between</option>
                        <option value="off"<?php     if (!isset($_GET['snatched']) || $_GET['snatched'] === 'off')     { echo ' selected="selected"'; } ?>>Off</option>
                    </select>
                    <input type="text" name="snatched1" size="6" value="<?=display_str($_GET['snatched1'])?>" />
                    <input type="text" name="snatched2" size="6" value="<?=display_str($_GET['snatched2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Seeding:</td>
                <td width="30%">
                    <select name="seeding">
                        <option value="equal"<?php   if (isset($_GET['seeding']) && $_GET['seeding'] === 'equal')   { echo ' selected="selected"'; } ?>>Equal</option>
                        <option value="above"<?php   if (isset($_GET['seeding']) && $_GET['seeding'] === 'above')   { echo ' selected="selected"'; } ?>>Above</option>
                        <option value="below"<?php   if (isset($_GET['seeding']) && $_GET['seeding'] === 'below')   { echo ' selected="selected"'; } ?>>Below</option>
                        <option value="between"<?php if (isset($_GET['seeding']) && $_GET['seeding'] === 'between') { echo ' selected="selected"'; } ?>>Between</option>
                        <option value="off"<?php     if (!isset($_GET['seeding']) || $_GET['seeding'] === 'off')     { echo ' selected="selected"'; } ?>>Off</option>
                    </select>
                    <input type="text" name="seeding1" size="6" value="<?=display_str($_GET['seeding1'])?>" />
                    <input type="text" name="seeding2" size="6" value="<?=display_str($_GET['seeding2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="Units are GiB">Data Uploaded:</td>
                <td width="30%">
                    <select name="uploaded">
                        <?= option('uploaded', 'equal', 'Equal') ?>
                        <?= option('uploaded', 'above', 'Above') ?>
                        <?= option('uploaded', 'below', 'Below') ?>
                        <?= option('uploaded', 'between', 'Between') ?>
                        <?= option('uploaded', 'buffer', 'Buffer') ?>
                    </select>
                    <input type="text" name="uploaded1" size="6" value="<?=display_str($_GET['uploaded1'])?>" />
                    <input type="text" name="uploaded2" size="6" value="<?=display_str($_GET['uploaded2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="Units are GiB">Data Downloaded:</td>
                <td width="30%">
                    <select name="downloaded">
                        <?= option('downloaded', 'equal', 'Equal') ?>
                        <?= option('downloaded', 'above', 'Above') ?>
                        <?= option('downloaded', 'below', 'Below') ?>
                        <?= option('downloaded', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="downloaded1" size="6" value="<?=display_str($_GET['downloaded1'])?>" />
                    <input type="text" name="downloaded2" size="6" value="<?=display_str($_GET['downloaded2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="Units are GiB">Request Bounty:</td>
                <td width="30%">
                    <select name="bounty">
                        <?= option('bounty', 'equal', 'Equal') ?>
                        <?= option('bounty', 'above', 'Above') ?>
                        <?= option('bounty', 'below', 'Below') ?>
                        <?= option('bounty', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="bounty1" size="6" value="<?=display_str($_GET['bounty1'])?>" />
                    <input type="text" name="bounty2" size="6" value="<?=display_str($_GET['bounty2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Ratio:</td>
                <td width="30%">
                    <select name="ratio">
                        <?= option('ratio', 'equal', 'Equal') ?>
                        <?= option('ratio', 'above', 'Above') ?>
                        <?= option('ratio', 'below', 'Below') ?>
                        <?= option('ratio', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="ratio1" size="6" value="<?=display_str($_GET['ratio1'])?>" />
                    <input type="text" name="ratio2" size="6" value="<?=display_str($_GET['ratio2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr"># of invites:</td>
                <td>
                    <select name="invites">
                        <?= option('invites', 'equal', 'Equal') ?>
                        <?= option('invites', 'above', 'Above') ?>
                        <?= option('invites', 'below', 'Below') ?>
                        <?= option('invites', 'between', 'Between') ?>
                    </select>
                    <input type="text" name="invites1" size="6" value="<?=display_str($_GET['invites1'])?>" />
                    <input type="text" name="invites2" size="6" value="<?=display_str($_GET['invites2'])?>" />
                </td>
                </tr>

                <tr>
                <td width="30%" class="label nobr"># of invitees:</td>
                <td>
                    <select name="invitees">
                        <option value="equal" <?=isset($_GET['invitees']) && $_GET['invitees'] == 'equal' ? 'selected' : ''?>>Equal</option>
                        <option value="above" <?=isset($_GET['invitees']) && $_GET['invitees'] == 'above' ? 'selected' : ''?>>Above</option>
                        <option value="below" <?=isset($_GET['invitees']) && $_GET['invitees'] == 'below' ? 'selected' : ''?>>Below</option>
                        <option value="between" <?=isset($_GET['invitees']) && $_GET['invitees'] == 'between' ? 'selected' : ''?>>Between</option>
                        <option value="off" <?=!isset($_GET['invitees']) || $_GET['invitees'] == 'off' ? 'selected' : ''?>>Off</option>
                    </select>
                    <input type="text" name="invitees1" size="6" value="<?=display_str($_GET['invitees1'])?>" />
                    <input type="text" name="invitees2" size="6" value="<?=display_str($_GET['invitees2'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr"># of emails:</td>
                <td>
                    <select name="emails_opt">
                        <?= option('emails_opt', 'equal', 'Equal') ?>
                        <?= option('emails_opt', 'above', 'Above') ?>
                        <?= option('emails_opt', 'below', 'Below') ?>
                    </select>
                    <input type="text" name="email_cnt" size="6" value="<?=display_str($_GET['email_cnt'])?>" />
                </td>
                </tr>

            </table></td>
            <!-- col3 -->
            <td style="vertical-align:top;"><table class="layout">

                <tr>
                <td class="label nobr">Primary class:</td>
                <td>
                    <select name="class[]" size="3" multiple="multiple">
<?php foreach ($ClassLevels as $Class) {
    if ($Class['Secondary']) {
        continue;
    }
?>
                        <option value="<?=$Class['ID'] ?>"<?= in_array($Class['ID'], $_GET['class'] ?? []) ? ' selected="selected"' : ''
                            ?>><?=Format::cut_string($Class['Name'], 10, 1, 1).' ('.$Class['Level'].')'?></option>
<?php } ?>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Secondary class:</td>
                <td>
                    <select name="secclass">
                        <option value=""<?php if ($_GET['secclass'] === '') { echo ' selected="selected"'; } ?>>Don't Care</option>
<?php
$Secondaries = [];
// Neither level nor ID is particularly useful when searching secondary classes, so let's do some
// kung-fu to sort them alphabetically.
$fnc = function($Class1, $Class2) { return strcmp($Class1['Name'], $Class2['Name']); };
foreach ($ClassLevels as $Class) {
    if (!$Class['Secondary']) {
        continue;
    }
    $Secondaries[] = $Class;
}
usort($Secondaries, $fnc);
foreach ($Secondaries as $Class) {
?>
                        <option value="<?=$Class['ID'] ?>"<?php if ($_GET['secclass'] === $Class['ID']) { echo ' selected="selected"'; } ?>><?=Format::cut_string($Class['Name'], 20, 1, 1)?></option>
<?php } ?>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Enabled:</td>
                <td>
                    <select name="enabled">
                        <option value=""<?php  if ($_GET['enabled'] === '')  { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="0"<?php if ($_GET['enabled'] === '0') { echo ' selected="selected"'; } ?>>Unconfirmed</option>
                        <option value="1"<?php if ($_GET['enabled'] === '1') { echo ' selected="selected"'; } ?>>Enabled</option>
                        <option value="2"<?php if ($_GET['enabled'] === '2') { echo ' selected="selected"'; } ?>>Disabled</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Donor:</td>
                <td>
                    <select name="donor">
                        <option value=""<?php    if ($_GET['donor'] === '')    { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="yes"<?php if ($_GET['donor'] === 'yes') { echo ' selected="selected"'; } ?>>Yes</option>
                        <option value="no"<?php  if ($_GET['donor'] === 'no')  { echo ' selected="selected"'; } ?>>No</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Warned:</td>
                <td>
                    <select name="warned">
                        <option value=""<?php    if ($_GET['warned'] === '')    { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="isnotnull"<?php if ($_GET['warned'] === 'isnotnull') { echo ' selected="selected"'; } ?>>Yes</option>
                        <option value="isnull"<?php  if ($_GET['warned'] === 'isnull')  { echo ' selected="selected"'; } ?>>No</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Locked Account:</td>
                <td>
                    <select name="lockedaccount">
                        <option value="any"<?php if ($_GET['lockedaccount'] == 'any') { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="locked"<?php if ($_GET['lockedaccount'] == 'locked') { echo ' selected="selected"'; } ?>>Locked</option>
                        <option value="unlocked"<?php if ($_GET['lockedaccount'] == 'unlocked') { echo ' selected="selected"'; } ?>>Unlocked</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Disabled invites:</td>
                <td>
                    <select name="disabled_invites">
                        <option value=""<?php    if ($_GET['disabled_invites'] === '')    { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="yes"<?php if ($_GET['disabled_invites'] === 'yes') { echo ' selected="selected"'; } ?>>Yes</option>
                        <option value="no"<?php  if ($_GET['disabled_invites'] === 'no')  { echo ' selected="selected"'; } ?>>No</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Disabled uploads:</td>
                <td>
                    <select name="disabled_uploads">
                        <option value=""<?php    if (isset($_GET['disabled_uploads']) && $_GET['disabled_uploads'] === '')    { echo ' selected="selected"'; } ?>>Don't Care</option>
                        <option value="yes"<?php if (isset($_GET['disabled_uploads']) && $_GET['disabled_uploads'] === 'yes') { echo ' selected="selected"'; } ?>>Yes</option>
                        <option value="no"<?php  if (isset($_GET['disabled_uploads']) && $_GET['disabled_uploads'] === 'no')  { echo ' selected="selected"'; } ?>>No</option>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label nobr">Stylesheet:</td>
                <td>
                    <select name="stylesheet" id="stylesheet">
                        <option value="">Don't Care</option>
<?php foreach ($Stylesheets as $Style) { ?>
                        <option value="<?=$Style['ID']?>"<?php Format::selected('stylesheet',$Style['ID']); ?>><?=$Style['ProperName']?></option>
<?php } ?>
                    </select>
                </td>
                </tr>

                <tr>
                <td class="label tooltip nobr" title="Two-letter codes as defined in ISO 3166-1 alpha-2">Country code:</td>
                <td width="30%">
                    <select name="cc_op">
                        <option value="equal"<?php     if ($_GET['cc_op'] === 'equal')     { echo ' selected="selected"'; } ?>>Equals</option>
                        <option value="not_equal"<?php if ($_GET['cc_op'] === 'not_equal') { echo ' selected="selected"'; } ?>>Not equal</option>
                    </select>
                    <input type="text" name="cc" size="2" value="<?=display_str($_GET['cc'])?>" />
                </td>
                </tr>

                <tr>
                <td class="label nobr">Search type:</td>
                <td>
                    <ul class="options_list nobullet">
                        <li>
                            <input type="radio" name="matchtype" id="strict_match_type" value="strict"<?php if ($_GET['matchtype'] == 'strict' || !$_GET['matchtype']) { echo ' checked="checked"'; } ?> />
                            <label class="tooltip" title="A &quot;strict&quot; search uses no wildcards in search fields, and it is analogous to &#96;grep -E &quot;&circ;SEARCHTERM&#36;&quot;&#96;" for="strict_match_type">Strict</label>
                        </li>
                        <li>
                            <input type="radio" name="matchtype" id="fuzzy_match_type" value="fuzzy"<?php if ($_GET['matchtype'] == 'fuzzy' || !$_GET['matchtype']) { echo ' checked="checked"'; } ?> />
                            <label class="tooltip" title="A &quot;fuzzy&quot; search automatically prepends and appends wildcards to search strings, except for IP address searches, unless the search string begins or ends with a &quot;&#124;&quot; (pipe). It is analogous to a vanilla grep search (except for the pipe stuff)." for="fuzzy_match_type">Fuzzy</label>
                        </li>
                        <li>
                            <input type="radio" name="matchtype" id="regexp_match_type" value="regexp"<?php if ($_GET['matchtype'] == 'regexp') { echo ' checked="checked"'; } ?> />
                            <label class="tooltip" title="A &quot;regexp&quot; search uses MySQL's regular expression syntax." for="regexp_match_type">Regexp</label>
                        </li>
                    </ul>
                </td>
                </tr>

            </table></td>
        </tr>
        </table>
        <!-- end -->

        Results ordered <select name="order">
<?php foreach (array_shift($OrderVals) as $Cur) { ?>
            <option value="<?=$Cur?>"<?php if (isset($_GET['order']) && $_GET['order'] == $Cur || (!isset($_GET['order']) && $Cur == 'Joined')) { echo ' selected="selected"'; } ?>><?=$Cur?></option>
<?php } ?>
        </select>
        <select name="way">
<?php foreach (array_shift($WayVals) as $Cur) { ?>
            <option value="<?=$Cur?>"<?php if (isset($_GET['way']) && $_GET['way'] == $Cur || (!isset($_GET['way']) && $Cur == 'Descending')) { echo ' selected="selected"'; } ?>><?=$Cur?></option>
<?php } ?>
        </select>
        <input type="submit" value=" Search " />
    </form>
</div>
<?php
$Results = $DB->prepared_query($SQL, ...array_merge($Args, $HavingArgs));
$DB->query('SELECT FOUND_ROWS()');
list($NumResults) = $DB->next_record();
$DB->set_query_id($Results);
?>
<div class="linkbox">
<?php
$Pages = Format::get_pages($Page, $NumResults, USERS_PER_PAGE, 11);
echo $Pages;
?>
</div>
<div class="box pad center">
    <h2><?=number_format($NumResults)?> results</h2>
    <table width="100%">
        <tr class="colhead">
            <td>Username</td>
            <td>Email</td>
            <td>Joined</td>
            <td>Last seen</td>
            <td>Upload</td>
            <td>Download</td>
            <td>Ratio</td>
            <td>Bounty</td>
            <td>Downloads</td>
            <td>Snatched</td>
            <td>Seeding</td>
            <td>Invites</td>
<?php if (isset($_GET['invitees']) && $_GET['invitees'] != 'off') { ?>
            <td>Invitees</td>
<?php } ?>
        </tr>
<?php
while (list($UserID, $Username, $Uploaded, $Downloaded, $Downloads, $Bounty, $Seeding, $Snatched, $Invitees, $Class, $Email, $Enabled, $Invites, $DisableInvites, $Warned, $Donor, $JoinDate, $LastAccess) = $DB->next_record()) { ?>
        <tr>
            <td><?=Users::format_username($UserID, true, true)?></td>
            <td><?=display_str($Email)?></td>
            <td><?=time_diff($JoinDate)?></td>
            <td><?=time_diff($LastAccess)?></td>
            <td><?=Format::get_size($Uploaded)?></td>
            <td><?=Format::get_size($Downloaded)?></td>
            <td><?=Format::get_ratio_html($Uploaded, $Downloaded)?></td>
            <td><?=Format::get_size($Bounty)?></td>
            <td><?=number_format((int)$Downloads)?></td>
            <td><?=(is_numeric($Snatched) ? number_format($Snatched) : display_str($Snatched))?></td>
            <td><?=(is_numeric($Seeding) ? number_format($Seeding) : display_str($Seeding))?></td>
            <td><?php if ($DisableInvites) { echo 'X'; } else { echo number_format($Invites); } ?></td>
<?php if (isset($_GET['invitees']) && $_GET['invitees'] != 'off') { ?>
            <td><?=number_format($Invitees)?></td>
<?php } ?>
        </tr>
<?php } ?>
    </table>
</div>
<div class="linkbox">
<?=$Pages?>
</div>
<?php
View::show_footer();
