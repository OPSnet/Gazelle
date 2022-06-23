<?php

if (isset($_GET['search'])) {
    $_GET['search'] = trim($_GET['search']);
}

if (!empty($_GET['search'])) {
    if (preg_match(IP_REGEXP, $_GET['search'])) {
        $_GET['ip'] = $_GET['search'];
    } elseif (preg_match(EMAIL_REGEXP, $_GET['search'])) {
        $_GET['email'] = $_GET['search'];
    } elseif (preg_match(USERNAME_REGEXP, $_GET['search'], $match)) {
        $username = $match['username'];
        $found = (new Gazelle\Manager\User)->findByUsername($username);
        if ($found) {
            header('Location: ' . $found->location());
            exit;
        }
        $_GET['username'] = $username;
    } else {
        $_GET['comment'] = $_GET['search'];
    }
}

foreach (['ip', 'email', 'username', 'comment'] as $field) {
    if (isset($_GET[$field])) {
        $_GET[$field] = trim($_GET[$field]);
    }
}

class SQLMatcher {
    protected $key;

    public function __construct($key) {
        $this->key = $key;
    }

    public function matchField($field) {
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
        ($_GET[$field] ?? '') === $value ? ' selected="selected"' : '',
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
    'Ratio'      => '(uls1.Uploaded / uls1.Downloaded)',
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

$paginator = new Gazelle\Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$Stylesheets = (new \Gazelle\Manager\Stylesheet)->list();

$matchMode = ($_GET['matchtype'] ?? 'fuzzy');
$searchDisabledInvites = (isset($_GET['disabled_invites']) && $_GET['disabled_invites'] != '');
$searchDisabledUploads = (isset($_GET['disabled_uploads']) && $_GET['disabled_uploads'] != '');
$searchLockedAccount = (($_GET['lockedaccount'] ?? '') == 'locked');
$showInvited = (($_GET['invited'] ?? 'off') !== 'off');

if (!empty($_GET)) {
    $emailHistoryChecked = !empty($_GET['email_history']);
    $disabledIpChecked = !empty($_GET['disabled_ip']);
    $ipHistoryChecked = !empty($_GET['ip_history']);
    $trackerLiveSource = ($_GET['tracker-src'] ?? 'live') == 'live';
    $DateRegexp = ['regexp' => '/\d{4}-\d{2}-\d{2}/'];
    $ClassIDs = [];
    $SecClassIDs = [];
    $Classes = (new Gazelle\Manager\User)->classList();
    foreach ($Classes as $id => $value) {
        if ($value['Secondary']) {
            $SecClassIDs[] = $id;
        } else {
            $ClassIDs[] = $id;
        }
    }

    $validator = new Gazelle\Util\Validator;
    $validator->setFields([
        ['avatar', '0', 'string', 'Avatar URL too long', ['maxlength' => 512]],
        ['bounty', '0', 'inarray', "Invalid bounty field", $OffNumberChoices],
        ['cc', '0', 'inarray', 'Invalid Country Code', ['maxlength' => 2]],
        ['comment', '0', 'string', 'Comment is too long.', ['maxlength' => 512]],
        ['disabled_invites', '0', 'inarray', 'Invalid disabled_invites field', $YesNo],
        ['disabled_uploads', '0', 'inarray', 'Invalid disabled_uploads field', $YesNo],
        ['donor', '0', 'inarray', 'Invalid donor field', $YesNo],
        ['downloaded', '0', 'inarray', 'Invalid downloaded field', $NumberChoices],
        ['enabled', '0', 'inarray', 'Invalid enabled field', ['inarray' => ['', 0, 1, 2]]],
        ['join1', '0', 'regexp', 'Invalid join1 field', $DateRegexp],
        ['join2', '0', 'regexp', 'Invalid join2 field', $DateRegexp],
        ['joined', '0', 'inarray', 'Invalid joined field', $DateChoices],
        ['lastactive', '0', 'inarray', 'Invalid lastactive field', $DateChoices],
        ['lastactive1', '0', 'regexp', 'Invalid lastactive1 field', $DateRegexp],
        ['lastactive2', '0', 'regexp', 'Invalid lastactive2 field', $DateRegexp],
        ['lockedaccount', '0', 'inarray', 'Invalid locked account field', ['inarray' => ['any', 'locked', 'unlocked']]],
        ['matchtype', '0', 'inarray', 'Invalid matchtype field', ['inarray' => ['strict', 'fuzzy', 'regexp']]],
        ['order', '0', 'inarray', 'Invalid ordering', $OrderVals],
        ['passkey', '0', 'string', 'Invalid passkey', ['maxlength' => 32]],
        ['ratio', '0', 'inarray', 'Invalid ratio field', $NumberChoices],
        ['secclass', '0', 'inarray', 'Invalid class', ['inarray' => $SecClassIDs]],
        ['seeding', '0', 'inarray', "Invalid seeding field", $OffNumberChoices],
        ['snatched', '0', 'inarray', "Invalid snatched field", $OffNumberChoices],
        ['stylesheet', '0', 'inarray', 'Invalid stylesheet', ['inarray' => array_keys($Stylesheets)]],
        ['uploaded', '0', 'inarray', 'Invalid uploaded field', $NumberChoices],
        ['warned', '0', 'inarray', 'Invalid warned field', $Nullable],
        ['way', '0', 'inarray', 'Invalid way', $WayVals],
    ]);
    if (!$validator->validate($_GET)) {
        error($validator->errorMessage());
    }

    $m = new SQLMatcher($matchMode);
    $Where = [];
    $Args = [];
    $Having = [];
    $HavingArgs = [];
    $Join = [];
    $Distinct = false;
    $Order = '';

    $invitedValue = $showInvited
        ? '(SELECT count(*) FROM users_info AS ui2 WHERE ui2.Inviter = um1.ID)'
        : "'X'";
    $seedingValue = ($_GET['seeding'] ?? 'off') == 'off'
        ? "'X'"
        : '(SELECT count(DISTINCT fid)
            FROM xbt_files_users xfu
            WHERE xfu.active = 1 AND xfu.remaining = 0 AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND xfu.uid = um1.ID)';
    $snatchesValue = ($_GET['snatched'] ?? 'off') == 'off'
        ? "'X'"
        : '(SELECT count(DISTINCT fid) FROM xbt_snatched AS xs WHERE xs.uid = um1.ID)';

    $columns = "
        um1.ID          AS user_id,
        $seedingValue  AS seeding,
        $snatchesValue AS snatches,
        $invitedValue  AS invited
    ";

    $from = "FROM users_main AS um1
    INNER JOIN permissions p ON (p.ID = um1.PermissionID)
    INNER JOIN users_leech_stats AS uls1 ON (uls1.UserID = um1.ID)
    INNER JOIN users_info AS ui1 ON (ui1.UserID = um1.ID)
    LEFT JOIN user_last_access AS ula ON (ula.user_id = um1.ID)
    ";

    if (!empty($_GET['username'])) {
        $Where[] = $m->matchField('um1.Username');
        $Args[] = $_GET['username'];
    }

    if (!empty($_GET['email'])) {
        if (isset($_GET['email_history'])) {
            $Distinct = true;
            $Join['he'] = 'INNER JOIN users_history_emails AS he ON (he.UserID = um1.ID)';
            $Where[] = $m->matchField('he.Email');
        } else {
            $Where[] = $m->matchField('um1.Email');
        }
        $Args[] = $_GET['email'];
    }

    if (isset($_GET['email_opt']) && isset($_GET['email_cnt']) && strlen($_GET['email_cnt'])) {
        $Where[] = sprintf('um1.ID IN (%s)',
            $m->op("
                SELECT UserID FROM users_history_emails GROUP BY UserID HAVING count(DISTINCT Email)
                ", $_GET['emails_opt']
            )
        );
        $Args[] = (int)$_GET['email_cnt'];
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

    if ($searchLockedAccount) {
        $Join['la'] .= 'INNER JOIN locked_accounts AS la ON (la.UserID = um1.ID)';
    }
    elseif (isset($_GET['lockedaccount']) && $_GET['lockedaccount'] == 'unlocked') {
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
        $Where[] = $m->matchField('ui1.AdminComment');
        $Args[] = $_GET['comment'];
    }

    if (!empty($_GET['lastfm'])) {
        $Distinct = true;
        $Join['lfm'] = 'INNER JOIN lastfm_users AS lfm ON (lfm.ID = um1.ID)';
        $Where[] = $m->matchField('lfm.Username');
        $Args[] = $_GET['lastfm'];
    }

    if (isset($_GET['invites']) && !empty($_GET['invites']) && isset($_GET['invites1']) && strlen($_GET['invites1'])) {
        $op = $_GET['invites'];
        $Where[] = $m->op('um1.Invites', $op);
        $Args = array_merge($Args, [$_GET['invites1']], ($op === 'between' ? [$_GET['invites2']] : []));
    }

    if ($showInvited && isset($_GET['invited1']) && strlen($_GET['invited1'])) {
        $op = $_GET['invited'];
        $Where[] = "um1.ID IN ("
            . $m->op("SELECT umi.ID FROM users_info uii INNER JOIN users_main umi ON (umi.ID = uii.Inviter) GROUP BY umi.ID HAVING count(*)", $op)
            . ")";
        $Args = array_merge($Args, [$_GET['invited1']], ($op === 'between' ? [$_GET['invited2']] : []));
    }

    if ($searchDisabledInvites) {
        $Where[] = 'ui1.DisableInvites = ?';
        $Args[] = $_GET['disabled_invites'] == 'yes' ? '1' : '0';
    }

    if ($searchDisabledUploads) {
        $Where[] = 'ui1.DisableUpload = ?';
        $Args[] = $_GET['disabled_uploads'] === 'yes' ? '1' : '0';
    }

    if (isset($_GET['joined']) && !empty($_GET['joined']) && isset($_GET['join1']) && !empty($_GET['join1'])) {
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

    if (isset($_GET['lastactive']) && !empty($_GET['lastactive']) && isset($_GET['lastactive1']) && !empty($_GET['lastactive1'])) {
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

    if (isset($_GET['ratio']) && !empty($_GET['ratio']) && isset($_GET['ratio1']) && strlen($_GET['ratio1'])) {
        $Decimals = strlen(array_pop(explode('.', $_GET['ratio1'])));
        if (!$Decimals) {
            $Decimals = 0;
        }
        $op = $_GET['ratio'];
        $Where[] = $m->op('CASE WHEN uls1.Downloaded = 0 then 0 ELSE round(uls1.Uploaded/uls1.Downloaded, ?) END', $op);
        $Args = array_merge($Args, [$Decimals, $_GET['ratio1']], ($op === 'between' ? [$_GET['ratio2']] : []));
    }

    if (isset($_GET['bounty']) && !empty($_GET['bounty']) && $_GET['bounty'] !== 'off' && isset($_GET['bounty1']) && strlen($_GET['bounty1'])) {
        $op = $_GET['bounty'];
        $Where[] = $m->op('(SELECT sum(Bounty) FROM requests_votes rv WHERE rv.UserID = um1.ID)', $op);
        $Args = array_merge($Args, [$_GET['bounty1'] * 1024 ** 3], ($op === 'between' ? [$_GET['bounty2'] * 1024 ** 3] : []));
    }

    if (isset($_GET['downloads']) && !empty($_GET['downloads']) && $_GET['downloads'] !== 'off' && isset($_GET['downloads1']) && strlen($_GET['downloads1'])) {
        $op = $_GET['downloads'];
        $Where[] = $m->op('(SELECT count(DISTINCT TorrentID) FROM users_downloads ud WHERE ud.UserID = um1.ID)', $op);
        $Args = array_merge($Args, [$_GET['downloads1']], ($op === 'between' ? [$_GET['downloads2']] : []));
    }

    if (isset($_GET['seeding']) && $_GET['seeding'] !== 'off' && isset($_GET['seeding1'])) {
        $op = $_GET['seeding'];
        $Where[] = $m->op('(SELECT count(DISTINCT fid)
            FROM xbt_files_users xfu
            WHERE xfu.active = 1 AND xfu.remaining = 0 AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND xfu.uid = um1.ID)', $op);
        $Args = array_merge($Args, [$_GET['seeding1']], ($op === 'between' ? [$_GET['seeding2']] : []));
    }

    if (isset($_GET['snatched']) && $_GET['snatched'] !== 'off' && isset($_GET['snatched1'])) {
        $op = $_GET['snatched'];
        $Where[] = $m->op('(SELECT count(DISTINCT fid) FROM xbt_snatched AS xs WHERE xs.uid = um1.ID)', $op);
        $Args = array_merge($Args, [$_GET['snatched1']], ($op === 'between' ? [$_GET['snatched2']] : []));
    }

    if (isset($_GET['uploaded']) && !empty($_GET['uploaded']) && isset($_GET['uploaded1']) && strlen($_GET['uploaded1'])) {
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

    if (isset($_GET['downloaded']) && !empty($_GET['downloaded']) && isset($_GET['downloaded1']) && strlen($_GET['downloaded1'])) {
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

    if (isset($_GET['enabled']) && $_GET['enabled'] != '') {
        $Where[] = 'um1.Enabled = ?';
        $Args[] = $_GET['enabled'];
    }

    if (isset($_GET['class']) && is_array($_GET['class'])) {
        $Where[] = 'um1.PermissionID IN (' . placeholders($_GET['class']) . ')';
        $Args = array_merge($Args, $_GET['class']);
    }

    if (isset($_GET['secclass']) && $_GET['secclass'] != '') {
        $Join['ul'] = 'INNER JOIN users_levels AS ul ON (um1.ID = ul.UserID)';
        $Where[] = 'ul.PermissionID = ?';
        $Args[] = $_GET['secclass'];
    }

    if (isset($_GET['donor']) && !empty($_GET['donor'])) {
        $Where[] = 'ui1.Donor = ?';
        $Args[] = $_GET['donor'] === 'yes' ? '1' : '0';
    }

    if (isset($_GET['warned']) && !empty($_GET['warned'])) {
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

    if (isset($_GET['passkey']) && !empty($_GET['passkey'])) {
        $Where[] = $m->matchField('um1.torrent_pass');
        $Args[] = $_GET['passkey'];
    }

    if (isset($_GET['avatar']) && !empty($_GET['avatar'])) {
        $Where[] = $m->matchField('ui1.Avatar');
        $Args[] = $_GET['avatar'];
    }

    if (isset($_GET['stylesheet']) && !empty($_GET['stylesheet'])) {
        $Where[] = $m->matchField('ui1.StyleID');
        $Args[] = $_GET['stylesheet'];
    }

    if ($OrderTable[$_GET['order'] ?? 'Joined'] && $WayTable[$_GET['way'] ?? 'Descending']) {
        $Order = 'ORDER BY ' . $OrderTable[$_GET['order'] ?? 'Joined'] . ' ' . $WayTable[$_GET['way'] ?? 'Descending'];
    }

    //---------- Build the query
    $SQL = "SELECT count(*) $from " . implode("\n", $Join);
    if (count($Where)) {
        $SQL .= "WHERE " . implode("\nAND ", $Where);
    }
    if ($Distinct) {
        $SQL .= "\nGROUP BY um1.ID";
    }
    if (count($Having)) {
        $SQL .= "\nHAVING " . implode(' AND ', $Having);
    }
    $paginator->setTotal($DB->scalar($SQL, ...array_merge($Args, $HavingArgs)) ?? 0);

    $SQL = "SELECT $columns $from " . implode("\n", $Join);
    if (count($Where)) {
        $SQL .= "WHERE " . implode("\nAND ", $Where);
    }
    if ($Distinct) {
        $SQL .= "\nGROUP BY um1.ID";
    }
    if (count($Having)) {
        $SQL .= "\nHAVING " . implode(' AND ', $Having);
    }
    $SQL .= "\n$Order LIMIT ? OFFSET ?";

    $DB->prepared_query($SQL, ...array_merge($Args, $HavingArgs, [$paginator->limit(), $paginator->offset()]));
    $Results = $DB->to_array(false, MYSQLI_ASSOC, false);
    $userman = new Gazelle\Manager\User;
    foreach ($Results as &$r) {
        $r['user'] = $userMan->findById($r['user_id']);
    }
    unset($r);
}

// Neither level nor ID is particularly useful when searching secondary classes, so sort them alphabetically.
$ClassLevels = (new Gazelle\Manager\User)->classLevelList();
$Secondaries = array_filter($ClassLevels, fn ($c) => $c['Secondary'] == '1');
usort($Secondaries, fn($c1, $c2) => $c1['Name'] <=> $c2['Name']);

echo $Twig->render('admin/advanced-user-search.twig', [
    'page'          => $Results,
    'paginator'     => $paginator,
    'show_invited'  => $showInvited,
    'url_stem'      => (new Gazelle\User\Stylesheet($Viewer))->imagePath(),
    'viewer'        => $Viewer,
    'input'         => $_GET,

    'tracker_live_source' => $trackerLiveSource,
    'check_disabled_ip'   => $disabledIpChecked,
    'check_ip_history'    => $ipHistoryChecked,
    'check_email_history' => $emailHistoryChecked,

    // third column
    'primary_class'    => array_filter($ClassLevels, fn ($c) => $c['Secondary'] == '0'),
    'secondary_class'  => $Secondaries,
    'stylesheet'       => $Stylesheets,
    'match_mode'       => $matchMode,

    // sorting widgets
    'field_by'      => array_shift($OrderVals),
    'order_by'      => array_shift($WayVals),
]);
