<?php
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$userMan = new Manager\User();

if (isset($_GET['search'])) {
    $_GET['search'] = trim($_GET['search']);
}

if (!empty($_GET['search'])) {
    if (preg_match(IP_REGEXP, $_GET['search'])) {
        $_GET['ip'] = $_GET['search'];
    } elseif (preg_match(EMAIL_REGEXP, $_GET['search'])) {
        $_GET['email'] = $_GET['search'];
    } elseif (str_starts_with($_GET['search'], '#')) {
        $found = $userMan->findById((int)substr($_GET['search'], 1));
        if ($found) {
            header('Location: ' . $found->location());
            exit;
        }
    } elseif (preg_match(USERNAME_REGEXP, $_GET['search'], $match)) {
        $username = $match['username'];
        $found = $userMan->findByUsername($username);
        if ($found) {
            header('Location: ' . $found->location());
            exit;
        }
        $_GET['username'] = $username;
    } else {
        $_GET['comment'] = $_GET['search'];
    }
}

foreach (['ip', 'email', 'username', 'comment', 'ext_profile'] as $field) {
    if (isset($_GET[$field])) {
        $_GET[$field] = trim($_GET[$field]);
    }
}

function option(string $field, string $value, string $label): string {
    return sprintf('<option value="%s"%s>%s</option>',
        $value,
        ($_GET[$field] ?? '') === $value ? ' selected="selected"' : '',
        $label);
}

$orderBy = [
    'Joined'     => 'um1.created',
    'Last Seen'  => 'ula.last_access',
    'Seeding'    => 'Seeding',
    'Snatches'   => 'Snatches',
    'Email'      => 'um1."Email"',
    'Invites'    => 'um1."Invites"',
    'Username'   => 'um1."Username"',
];
$dir = ['Ascending' => 'ASC', 'Descending' => 'DESC'];

// Arrays, regexps, and all that fun stuff we can use for validation, form generation, etc
$orderByValue = ['inarray' => array_keys($orderBy)];
$dirValue = ['inarray' => array_keys($dir)];

$dateChoice = ['inarray' => ['on', 'before', 'after', 'between']];
$numberChoice = ['inarray' => ['equal', 'above', 'below', 'between', 'buffer']];
$offNumberChoice = ['inarray' => ['equal', 'above', 'below', 'between', 'buffer', 'off']];
$yesNo = ['inarray' => ['any', 'yes', 'no']];
$nullable = ['inarray' => ['any', 'isnull', 'isnotnull']];

$emailHistoryChecked = false;
$ipHistoryChecked = false;
$trackerLiveSource = true;

$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$stylesheet = new Manager\Stylesheet()->list();

$matchMode = ($_GET['matchtype'] ?? 'fuzzy');
$searchDisabledInvites = (isset($_GET['disabled_invites']) && $_GET['disabled_invites'] != '');
$searchDisabledUploads = (isset($_GET['disabled_uploads']) && $_GET['disabled_uploads'] != '');
$searchLockedAccount = (($_GET['lockedaccount'] ?? '') == 'locked');
$showInvited = (($_GET['invited'] ?? 'off') !== 'off');

if (empty($_GET)) {
    $result = [];
} else {
    $emailHistoryChecked = !empty($_GET['email_history']);
    $ipHistoryChecked    = !empty($_GET['ip_history']);
    $trackerLiveSource   = ($_GET['tracker-src'] ?? 'live') == 'live';
    $dateRegexp          = ['regexp' => '/\d{4}-\d{2}-\d{2}/'];
    $userclassList       = [];
    $secClassList        = [];
    foreach ($userMan->classList() as $id => $value) {
        if ($value['Secondary']) {
            $secClassList[] = $id;
        } else {
            $userclassList[] = $id;
        }
    }

    $validator = new Util\Validator();
    $validator->setFields([
        ['avatar', false, 'string', 'Avatar URL too long', ['maxlength' => 512]],
        ['bounty', false, 'inarray', "Invalid bounty field", $offNumberChoice],
        ['cc', false, 'inarray', 'Invalid Country Code', ['maxlength' => 2]],
        ['comment', false, 'string', 'Comment is too long.', ['maxlength' => 512]],
        ['disabled_invites', false, 'inarray', 'Invalid disabled_invites field', $yesNo],
        ['disabled_uploads', false, 'inarray', 'Invalid disabled_uploads field', $yesNo],
        ['downloaded', false, 'inarray', 'Invalid downloaded field', $numberChoice],
        ['enabled', false, 'inarray', 'Invalid enabled field', ['inarray' => ['', 0, 1, 2]]],
        ['ext_profile', false, 'string', 'External profile is too long.', ['maxlength' => 512]],
        ['join1', false, 'regexp', 'Invalid join1 field', $dateRegexp],
        ['join2', false, 'regexp', 'Invalid join2 field', $dateRegexp],
        ['joined', false, 'inarray', 'Invalid joined field', $dateChoice],
        ['lastactive', false, 'inarray', 'Invalid lastactive field', $dateChoice],
        ['lastactive1', false, 'regexp', 'Invalid lastactive1 field', $dateRegexp],
        ['lastactive2', false, 'regexp', 'Invalid lastactive2 field', $dateRegexp],
        ['lockedaccount', false, 'inarray', 'Invalid locked account field', ['inarray' => ['any', 'locked', 'unlocked']]],
        ['matchtype', false, 'inarray', 'Invalid matchtype field', ['inarray' => ['strict', 'fuzzy', 'regexp']]],
        ['order', false, 'inarray', 'Invalid ordering', $orderByValue],
        ['passkey', false, 'string', 'Invalid passkey', ['maxlength' => 32]],
        ['ratio', false, 'inarray', 'Invalid ratio field', $numberChoice],
        ['secclass', false, 'inarray', 'Invalid class', ['inarray' => $secClassList]],
        ['seeding', false, 'inarray', "Invalid seeding field", $offNumberChoice],
        ['snatched', false, 'inarray', "Invalid snatched field", $offNumberChoice],
        ['stylesheet', false, 'inarray', 'Invalid stylesheet', ['inarray' => array_keys($stylesheet)]],
        ['uploaded', false, 'inarray', 'Invalid uploaded field', $numberChoice],
        ['warned', false, 'inarray', 'Invalid warned field', $nullable],
        ['way', false, 'inarray', 'Invalid way', $dirValue],
    ]);
    if (!$validator->validate($_GET)) {
        Error400::error($validator->errorMessage());
    }

    $m        = new Search\User($matchMode);
    $where    = [];
    $args     = [];
    $join     = [];
    $distinct = false;
    $order    = '';

    $invitedValue = $showInvited
        ? '(SELECT count(*) FROM relay.users_main AS umi WHERE umi.inviter_user_id = um1."ID")'
        : "'X'";
    $seedingValue = ($_GET['seeding'] ?? 'off') == 'off'
        ? "'X'"
        : '(SELECT count(DISTINCT fid)
            FROM relay.xbt_files_users xfu
            WHERE xfu.active = 1 AND xfu.remaining = 0 AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND xfu.uid = um1."ID")';
    $snatchesValue = ($_GET['snatched'] ?? 'off') == 'off'
        ? "'X'"
        : '(SELECT count(DISTINCT fid) FROM relay.xbt_snatched AS xs WHERE xs.uid = um1."ID")';

    $columns = "
        um1.\"ID\"     AS user_id,
        $seedingValue  AS seeding,
        $snatchesValue AS snatches,
        $invitedValue  AS invited
    ";

    $from = 'FROM relay.users_main AS um1
    INNER JOIN relay.permissions p ON (p."ID" = um1."PermissionID")
    INNER JOIN relay.users_leech_stats AS uls1 ON (uls1."UserID" = um1."ID")
    INNER JOIN relay.users_info AS ui1 ON (ui1."UserID" = um1."ID")
    LEFT JOIN relay.user_last_access AS ula ON (ula.user_id = um1."ID")
    ';

    if (!empty($_GET['username'])) {
        $where[] = $m->matchField('um1."Username"');
        $args[] = $_GET['username'];
    }

    if (!empty($_GET['email'])) {
        if (isset($_GET['email_history'])) {
            $distinct = true;
            $join['he'] = 'INNER JOIN relay.users_history_emails AS he ON (he."UserID" = um1."ID")';
            $where[] = $m->matchField('he."Email"');
        } else {
            $where[] = $m->matchField('um1."Email"');
        }
        $args[] = $_GET['email'];
    }

    if (!empty($_GET['ext_profile'])) {
        $join['ep'] = 'INNER JOIN user_external_profile ep ON (ep.id_user = um1."ID")';
        $where[] = $m->matchField('ep.profile');
        $args[] = $_GET['ext_profile'];
    }

    if (isset($_GET['email_opt']) && isset($_GET['email_cnt']) && strlen($_GET['email_cnt'])) {
        $where[] = sprintf('um1."ID" IN (%s)',
            $m->op('
                SELECT "UserID" FROM relay.users_history_emails GROUP BY "UserID" HAVING count(DISTINCT "Email")
                ', $_GET['emails_opt']
            )
        );
        $args[] = (int)$_GET['email_cnt'];
    }

    if (!empty($_GET['ip'])) {
        if ($ipHistoryChecked) {
            $distinct = true;
            $join['ish'] = 'INNER JOIN ip_site_history AS ish ON (ish.id_user = um1."ID")';
            $where[] = $m->op('ish.ip', 'subnet');
        } else {
            $where[] = $m->leftMatch('um1."IP"');
        }
        $args[] = trim($_GET['ip']);
    }

    if ($searchLockedAccount) {
        $join['la'] = 'INNER JOIN relay.locked_accounts AS la ON (la."UserID" = um1."ID")';
    } elseif (isset($_GET['lockedaccount']) && $_GET['lockedaccount'] == 'unlocked') {
        $join['la'] = 'LEFT JOIN relay.locked_accounts AS la ON (la."UserID" = um1."ID")';
        $where[] = 'la."UserID" IS NULL';
    }

    if (!empty($_GET['cc'])) {
        $where[] = $m->op('um1.ipcc', $_GET['cc_op']);
        $args[] = trim($_GET['cc']);
    }

    if (!empty($_GET['tracker_ip'])) {
        $distinct = true;
        $join['xfu'] = $trackerLiveSource
            ? 'INNER JOIN relay.xbt_files_users AS xfu ON (um1."ID" = xfu.uid)'
            : 'INNER JOIN relay.xbt_snatched AS xfu ON (um1."ID" = xfu.uid)';
        $where[] = $m->leftMatch('xfu.ip');
        $args[] = trim($_GET['tracker_ip']);
    }

    if (!empty($_GET['comment'])) {
        $distinct = true;
        $join['audit'] = 'inner join user_audit_trail uat on (uat.id_user = um1."ID")';
        $where[] = "note_ts @@ plainto_tsquery('simple', ?)";
        $args[] = $_GET['comment'];
    }

    if (!empty($_GET['lastfm'])) {
        $distinct = true;
        $join['lfm'] = 'INNER JOIN relay.lastfm_users AS lfm ON (lfm."ID" = um1."ID")';
        $where[] = $m->matchField('lfm."Username"');
        $args[] = $_GET['lastfm'];
    }

    if (isset($_GET['invites']) && !empty($_GET['invites']) && isset($_GET['invites1']) && strlen($_GET['invites1'])) {
        $op = $_GET['invites'];
        $where[] = $m->op('um1."Invites"', $op);
        $args = array_merge($args, [$_GET['invites1']], ($op === 'between' ? [$_GET['invites2']] : []));
    }

    if ($showInvited && isset($_GET['invited1']) && strlen($_GET['invited1'])) {
        $op = $_GET['invited'];
        $where[] = 'um1.ID IN ('
            . $m->op('SELECT umi."ID"
                FROM relay.users_main umii
                INNER JOIN relay.users_main umi ON (umi."ID" = umii.inviter_user_id)
                GROUP BY umi."ID"
                HAVING count(*)', $op
            ) . ')';
        $args = array_merge($args, [$_GET['invited1']], ($op === 'between' ? [$_GET['invited2']] : []));
    }

    if ($searchDisabledInvites) {
        $allow = $_GET['disabled_invites'] == 'yes' ? '' : 'NOT ';
        $where[] = $allow . ' EXISTS(
            SELECT 1
            FROM relay.user_has_attr uha_invite
            WHERE uha_invite."UserID" = um1."ID"
                AND uha_invite."UserAttrID" = (SELECT "ID" FROM user_attr WHERE "Name" = ?)
            )';
        $args[] = 'disable-invites';
    }

    if ($searchDisabledUploads) {
        $allow = $_GET['disabled_uploads'] == 'yes' ? '' : 'NOT ';
        $where[] = $allow . ' EXISTS(
            SELECT 1
            FROM relay.user_has_attr uha_upload
            WHERE uha_upload."UserID" = um1."ID"
                AND uha_upload."UserAttrID" = (SELECT "ID" FROM user_attr WHERE "Name" = ?)
            )';
        $args[] = 'disable-upload';
    }

    if (isset($_GET['joined']) && !empty($_GET['joined']) && isset($_GET['join1']) && !empty($_GET['join1'])) {
        $op = $_GET['joined'];
        $where[] = $m->date('um1.created', $op);
        $args[] = $_GET['join1'];
        if ($op === 'on') {
            $args[] = $_GET['join1'];
        } elseif ($op === 'between') {
            $args[] = $_GET['join2'];
        }
    }

    if (isset($_GET['lastactive']) && !empty($_GET['lastactive']) && isset($_GET['lastactive1']) && !empty($_GET['lastactive1'])) {
        $op = $_GET['lastactive'];
        $where[] = $m->date('ula.last_access', $op);
        $args[] = $_GET['lastactive1'];
        if ($op === 'on') {
            $args[] = $_GET['lastactive1'];
        } elseif ($op === 'between') {
            $args[] = $_GET['lastactive2'];
        }
    }

    if (isset($_GET['ratio']) && !empty($_GET['ratio']) && isset($_GET['ratio1']) && strlen($_GET['ratio1'])) {
        $frac = explode('.', $_GET['ratio1']);
        $decimals = strlen(end($frac));
        if (!$decimals) {
            $decimals = 0;
        }
        $op = $_GET['ratio'];
        $where[] = $m->op('CASE WHEN uls1."Downloaded" = 0 then 0 ELSE round(uls1."Uploaded"/uls1."Downloaded", ?) END', $op);
        $args = array_merge($args, [$decimals, $_GET['ratio1']], ($op === 'between' ? [$_GET['ratio2']] : []));
    }

    if (isset($_GET['bounty']) && !empty($_GET['bounty']) && $_GET['bounty'] !== 'off' && isset($_GET['bounty1']) && strlen($_GET['bounty1'])) {
        $op = $_GET['bounty'];
        $where[] = $m->op('(SELECT us.request_created_size FROM relay.user_summary us WHERE us.user_id = um1."ID")', $op);
        $args = array_merge($args, [$_GET['bounty1'] * 1024 ** 3], ($op === 'between' ? [$_GET['bounty2'] * 1024 ** 3] : []));
    }

    if (isset($_GET['downloads']) && !empty($_GET['downloads']) && $_GET['downloads'] !== 'off' && isset($_GET['downloads1']) && strlen($_GET['downloads1'])) {
        $op = $_GET['downloads'];
        $where[] = $m->op('(SELECT count(DISTINCT "TorrentID") FROM relay.users_downloads ud WHERE ud."UserID" = um1."ID")', $op);
        $args = array_merge($args, [$_GET['downloads1']], ($op === 'between' ? [$_GET['downloads2']] : []));
    }

    if (isset($_GET['seeding']) && $_GET['seeding'] !== 'off' && isset($_GET['seeding1'])) {
        $op = $_GET['seeding'];
        $where[] = $m->op('(SELECT count(DISTINCT fid)
            FROM relay.xbt_files_users xfu
            WHERE xfu.active = 1 AND xfu.remaining = 0 AND xfu.mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                AND xfu.uid = um1."ID")', $op);
        $args = array_merge($args, [$_GET['seeding1']], ($op === 'between' ? [$_GET['seeding2']] : []));
    }

    if (isset($_GET['snatched']) && $_GET['snatched'] !== 'off' && isset($_GET['snatched1'])) {
        $op = $_GET['snatched'];
        $where[] = $m->op('(SELECT count(DISTINCT fid) FROM relay.xbt_snatched AS xs WHERE xs.uid = um1."ID")', $op);
        $args = array_merge($args, [$_GET['snatched1']], ($op === 'between' ? [$_GET['snatched2']] : []));
    }

    if (isset($_GET['uploaded']) && !empty($_GET['uploaded']) && isset($_GET['uploaded1']) && strlen($_GET['uploaded1'])) {
        $op = $_GET['uploaded'];
        if ($op === 'buffer') {
            $where[] = 'uls1."Uploaded" - uls1."Downloaded" BETWEEN ? AND ?';
            $args = array_merge($args, [0.9 * $_GET['uploaded1'] * 1024 ** 3, 1.1 * $_GET['uploaded1'] * 1024 ** 3]);
        } else {
            $where[] = $m->op('uls1."Uploaded"', $op);
            $args[] = $_GET['uploaded1'] * 1024 ** 3;
            if ($op === 'on') {
                $args[] = $_GET['uploaded1'] * 1024 ** 3;
            } elseif ($op === 'between') {
                $args[] = $_GET['uploaded2'] * 1024 ** 3;
            }
        }
    }

    if (isset($_GET['downloaded']) && !empty($_GET['downloaded']) && isset($_GET['downloaded1']) && strlen($_GET['downloaded1'])) {
        $op = $_GET['downloaded'];
        $where[] = $m->op('uls1."Downloaded"', $op);
        $args[] = $_GET['downloaded1'] * 1024 ** 3;
        if ($op === 'on') {
            $args[] = $_GET['downloaded1'] * 1024 ** 3;
        } elseif ($op === 'between') {
            $args[] = $_GET['downloaded2'] * 1024 ** 3;
        }
    }

    if (isset($_GET['enabled']) && $_GET['enabled'] != '') {
        $where[] = 'um1."Enabled" = ?';
        $args[] = $_GET['enabled'];
    }

    if (isset($_GET['class']) && is_array($_GET['class'])) {
        $where[] = 'um1."PermissionID" IN (' . placeholders($_GET['class']) . ')';
        $args = array_merge($args, $_GET['class']);
    }

    if (isset($_GET['secclass']) && $_GET['secclass'] != '') {
        $join['ul'] = 'INNER JOIN relay.users_levels AS ul ON (um1."ID" = ul."UserID")';
        $where[] = 'ul."PermissionID" = ?';
        $args[] = $_GET['secclass'];
    }

    if (isset($_GET['warned']) && !empty($_GET['warned'])) {
        $where[] = $m->op('ui1."Warned"', $_GET['warned']);
    }

    if (isset($_GET['passkey']) && !empty($_GET['passkey'])) {
        $where[] = $m->matchField('um1.torrent_pass');
        $args[] = $_GET['passkey'];
    }

    if (isset($_GET['avatar']) && !empty($_GET['avatar'])) {
        $where[] = $m->matchField('um1.avatar');
        $args[] = $_GET['avatar'];
    }

    if (isset($_GET['stylesheet']) && !empty($_GET['stylesheet'])) {
        $where[] = $m->matchField('um1.stylesheet_id');
        $args[] = $_GET['stylesheet'];
    }

    $order = 'ORDER BY ' . $orderBy[$_GET['order'] ?? 'Joined'] . ' ' . $dir[$_GET['way'] ?? 'Descending'];

    //---------- Build the query
    $query = "SELECT count(*) over () $from " . implode("\n", $join);
    if (count($where)) {
        $query .= " WHERE " . implode("\nAND ", $where);
    }
    if ($distinct) {
        $query .= ' GROUP BY um1."ID"';
    }

    $pg = new DB\Pg(PG_RO_DSN);
    $paginator->setTotal((int)$pg->scalar($query, ...$args));

    $query = "SELECT $columns $from " . implode("\n", $join);
    if (count($where)) {
        $query .= ' WHERE ' . implode("\nAND ", $where);
    }
    if ($distinct) {
        $query .= ' GROUP BY um1."ID", ' . implode(',', array_values($orderBy));
    }
    $query .= " $order LIMIT ? OFFSET ?";

    $result = $pg->all($query, ...array_merge($args, [$paginator->limit(), $paginator->offset()]));
    foreach ($result as &$r) {
        $r['user'] = $userMan->findById($r['user_id']);
    }
    unset($r);
}

// Neither level nor ID is particularly useful when searching secondary classes, so sort them alphabetically.
$userclassLevel = $userMan->classLevelList();
$secondary = array_filter($userclassLevel, fn ($c) => $c['Secondary'] == '1');
usort($secondary, fn($c1, $c2) => $c1['Name'] <=> $c2['Name']);

echo $Twig->render('admin/advanced-user-search.twig', [
    'page'          => $result,
    'paginator'     => $paginator,
    'show_invited'  => $showInvited,
    'url_stem'      => new User\Stylesheet($Viewer)->imagePath(),
    'viewer'        => $Viewer,
    'input'         => $_GET,

    'tracker_live_source' => $trackerLiveSource,
    'check_ip_history'    => $ipHistoryChecked,
    'check_email_history' => $emailHistoryChecked,

    // third column
    'primary_class'    => array_filter($userclassLevel, fn ($c) => $c['Secondary'] == '0'),
    'secondary_class'  => $secondary,
    'stylesheet'       => $stylesheet,
    'match_mode'       => $matchMode,

    // sorting widgets
    'field_by'      => array_shift($orderByValue),
    'order_by'      => array_shift($dirValue),
]);
