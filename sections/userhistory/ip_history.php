<?php

use Gazelle\Util\Time;

/************************************************************************
||------------|| User IP history page ||---------------------------||

This page lists previous IPs a user has connected to the site with. It
gets called if $_GET['action'] == 'ips'.

It also requires $_GET['userid'] in order to get the data for the correct
user.

************************************************************************/

if (!check_perms('users_view_ips')) {
    error(403);
}

$user = (new Gazelle\Manager\User)->findById((int)($_GET['userid'] ?? 0));
if (is_null($user)) {
    error(404);
}
$UserID = $user->id();

$UsersOnly = !empty($_GET['usersonly']);
$cond = [];
$args = [];

if (trim($_GET['ip'] ?? '') !== '') {
    $cond[] = 'uhi.IP REGEXP ?';
    $args[] = trim($_GET['ip']);
}

[$Page, $Limit] = Format::page_limit(IPS_PER_PAGE);
if (!$UsersOnly) {
    $from = "FROM users_history_ips uhi"
        . ($cond ? (' WHERE ' . implode(' AND ', $cond)) : '')
        . " GROUP BY uhi.IP";
} else {
    $cond[] = 'uhi.UserID = ?';
    $args[] = $UserID;
    $from = "FROM users_history_ips uhi
        INNER JOIN users_history_ips uhi_other ON (uhi_other.IP = uhi.IP AND uhi_other.UserID != uhi.UserID)
        WHERE " . implode(' AND ', $cond) . "
        GROUP BY uhi.IP";
}

$totalCommon = $DB->scalar("
    SELECT count(DISTINCT uhi.IP)
    $from
    ", ...$args
);
$DB->prepared_query("
    SELECT uhi.IP,
        min(uhi.StartTime) as StartTime,
        max(uhi.EndTime) as EndTime
    $from
    ORDER BY StartTime DESC
    LIMIT $Limit
    ", ...$args
);
$Results = $DB->to_array(false, MYSQLI_ASSOC);

if ($Results) {
    $IPs = $DB->collect('IP');
    $DB->prepared_query("
        SELECT UserID,
            IP,
            StartTime,
            EndTime
        FROM users_history_ips
        WHERE UserID NOT IN (0, ?)
            AND IP IN (" . placeholders($IPs) . ")
        ORDER BY StartTime DESC
        ", $UserID, ...$IPs
    );

    while ($Match = $DB->next_record(MYSQLI_ASSOC)) {
        $OtherIP = $Match['IP'];
        $OtherUserID = $Match['UserID'];
        if (!isset($IPMatchesUser[$OtherIP][$OtherUserID])) {
            $IPMatchesUser[$OtherIP][$OtherUserID] = 0;
        }
        if ($IPMatchesUser[$OtherIP][$OtherUserID] < 500) {
            $IPMatches[$OtherIP][] = $Match;
        } else {
            if (!isset($IPMatchesIgnored[$OtherIP][$OtherUserID])) {
                $IPMatchesIgnored[$OtherIP][$OtherUserID] = 0;
            }
            $IPMatchesIgnored[$OtherIP][$OtherUserID]++;
        }
        $IPMatchesUser[$OtherIP][$OtherUserID]++;
    }
}

$Pages = Format::get_pages($Page, $totalCommon, IPS_PER_PAGE);
View::show_header($user->username() . " &rsaquo; IP address history", 'iphist');
?>
<div class="thin">
    <div class="header">
        <h2><a href="user.php?id=<?=$UserID?>"><?= $user->username() ?></a> &rsaquo; IP address history</h2>
        <div class="linkbox">
<?php
if ($UsersOnly) { ?>
            <a href="userhistory.php?<?=Format::get_url(['usersonly'])?>" class="brackets">View all IP addresses</a>
<?php
} else { ?>
            <a href="userhistory.php?<?=Format::get_url()?>&amp;usersonly=1" class="brackets">View IP addresses with users</a>
<?php
} ?>
        </div>
<?php
if ($Pages) { ?>
        <div class="linkbox pager"><?=$Pages?></div>
<?php
} ?>
    </div>
    <table>
        <tr class="colhead">
            <td>IP address search</td>
        </tr>

        <tr><td>
            <form class="search_form" name="ip_log" method="get" action="">
                <input type="hidden" name="action" value="<?=$_GET['action']?>" />
                <input type="hidden" name="userid" value="<?=$UserID?>" />
<?php
if ($UsersOnly) { ?>
                <input type="hidden" name="usersonly" value="1" />
<?php
} ?>
                <input type="text" name="ip" value="<?=Format::form('ip')?>" />
                <input type="submit" value="Search" />
                Regexps may be used
            </form>
        </td></tr>
    </table>

    <table id="iphistory">
        <tr class="colhead">
            <td>IP address</td>
            <td>Started <a href="#" onclick="$('#iphistory .reltime').gtoggle(); $('#iphistory .abstime').gtoggle(); return false;" class="brackets">Toggle</a></td>
            <td>Ended</td>
            <td class="hidden">Ended</td>
            <td>Elapsed</td>
        </tr>
<?php
$Counter = 0;
$IPBanChecks = [];
$PrintedIPs = [];
$CanManageIPBans = check_perms('admin_manage_ipbans');
foreach ($Results as $Index => $Result) {
    $IP = $Result['IP'];
    $StartTime = $Result['StartTime'];
    $EndTime = $Result['EndTime'];
    if (!$Result['EndTime']) {
        $EndTime = sqltime();
    }
    $OtherUsers = isset($IPMatches[$IP]) ? $IPMatches[$IP] : [];
    $ElementID = 'ip_' . strtr($IP, '.', '-');
    $FirstOccurrence = !isset($IPIndexes[$IP]);
    if ($FirstOccurrence) {
        $IPIndexes[$IP] = $Index;
    }
?>
        <tr class="rowa" <?=$FirstOccurrence ? "id=\"$ElementID\"" : ''?>>
            <td>
                <?=$IP?> (<?=Tools::get_country_code_by_ajax($IP)?>)<?php
    if ($CanManageIPBans) {
        if (!isset($IPBanChecks[$IP])) {
            $IPv4Man = new \Gazelle\Manager\IPv4;
            if ($IPv4Man->isBanned($IP)) {
                $IPBanChecks[$IP] = true;
?>
                <strong>[Banned]</strong>
<?php
            } else {
                $IPBanChecks[$IP] = false;
?>
                <a id="<?=$Counter?>" href="#" onclick="Ban('<?=$IP?>', '<?=$Counter?>'); this.onclick = null; return false;" class="brackets">Ban</a>
<?php
            }
            $Counter++;
        }
    }
?>
                <br />
                <?=Tools::get_host_by_ajax($IP)?>
<?php
    if (!empty($OtherUsers)) {
        if ($FirstOccurrence || count($OtherUsers) <= 100) {
?>
                <a href="#" onclick="$('.otherusers' + <?=$Index?>).gtoggle(); return false;">(<?=count($OtherUsers)?>)</a>
<?php
        } else {
?>
                <a href="#<?=$ElementID?>" onclick="$('.otherusers' + <?=$IPIndexes[$IP]?>).gshow();">(<?=count($OtherUsers)?>)</a>
<?php
        }
    } else {
?>
                (0)
<?php
    }
?>
            </td>
            <td>
                <span class="reltime"><?=time_diff($StartTime)?></span>
                <span class="abstime hidden"><?=$StartTime?></span>
            </td>
            <td>
                <span class="reltime"><?=time_diff($EndTime)?></span>
                <span class="abstime hidden"><?=$EndTime?></span>
            </td>
            <td><?= Time::timeDiff($EndTime, 2, true, false, $StartTime, true) ?></td>
        </tr>
<?php
    if (!empty($OtherUsers) && ($FirstOccurrence || count($OtherUsers) < 100)) {
        $HideMe = (count($OtherUsers) > 10);
        foreach ($OtherUsers as $OtherUser) {
            if (!$OtherUser['EndTime']) {
                $OtherUser['EndTime'] = sqltime();
            }
?>
        <tr class="rowb otherusers<?=$Index?><?=($HideMe ? ' hidden' : '')?>">
            <td>&nbsp;&nbsp;&#187;&nbsp;<?=Users::format_username($OtherUser['UserID'], true, true, true)?></td>
            <td>
                <span class="reltime"><?=time_diff($OtherUser['StartTime'])?></span>
                <span class="hidden abstime"><?=$OtherUser['StartTime']?></span>
            </td>
            <td>
                <span class="reltime"><?=time_diff($OtherUser['EndTime'])?></span>
                <span class="hidden abstime"><?=$OtherUser['EndTime']?></span>
            </td>
            <td><?= Time::timeDiff($OtherUser['StartTime'], 2, true, false, $OtherUser['EndTime'], true) ?></td>
        </tr>
<?php
        }
        if (isset($IPMatchesIgnored[$IP])) {
            foreach ($IPMatchesIgnored[$IP] as $OtherUserID => $MatchCount) {
?>
        <tr class="rowb otherusers<?=$Index?><?=($HideMe ? ' hidden' : '')?>">
            <td colspan="4">&nbsp;&nbsp;&#187;&nbsp;<?=$MatchCount?> matches skipped for <?=Users::format_username($OtherUserID, false, false, false)?></td>
        </tr>
<?php
            }
        }
    }
}
?>
    </table>
    <div class="linkbox">
        <?=$Pages?>
    </div>
</div>
<?php
View::show_footer();
