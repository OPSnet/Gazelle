<?php
if (($Comments = $Cache->get_value('user_nrcomment_' . $UserID)) === false) {
    $DB->prepared_query("
        SELECT Page, count(*) as n
        FROM comments
        WHERE AuthorID = ?
        GROUP BY Page", $UserID);
    $Comments = $DB->to_pair('Page', 'n', false);
    $Cache->cache_value('user_nrcomment_' . $UserID, $Comments, 3600);
}

$participationStats = $Cache->get_value('user_participation_stats_' . $UserID);
$participationStats = false;
if ($participationStats  === false) {
    $NumCollages = $DB->scalar("
        SELECT count(*)
        FROM collages
        WHERE Deleted = '0'
            AND UserID = ?
        ", $UserID
    );

    $NumCollageContribs = $DB->scalar("
        SELECT count(DISTINCT ct.CollageID)
        FROM collages_torrents AS ct
        INNER JOIN collages c ON (c.ID = ct.CollageID)
        WHERE c.Deleted = '0'
            AND ct.UserID = ?
        ", $UserID
    );

    $UniqueGroups = $DB->scalar("
        SELECT IFNULL(Groups, 0)
        FROM users_summary
        WHERE UserID = ?
        ", $UserID
    );

    $PerfectFLACs = $DB->scalar("
        SELECT IFNULL(PerfectFlacs, 0)
        FROM users_summary
        WHERE UserID = ?
        ", $UserID
    );

    $ForumTopics = $DB->scalar("
        SELECT count(*)
        FROM forums_topics
        WHERE AuthorID = ?
        ", $UserID
    );
    $participationStats = [$NumCollages, $NumCollageContribs, $UniqueGroups, $PerfectFLACs, $ForumTopics];
    $Cache->cache_value('user_participation_stats_' . $UserID, $participationStats, 3600);
}
[$NumCollages, $NumCollageContribs, $UniqueGroups, $PerfectFLACs, $ForumTopics] = $participationStats;
?>
        <div class="box box_info box_userinfo_community">
            <div class="head colhead_dark">Community</div>
            <ul class="stats nobullet">
                <li id="comm_posts">Forum threads: <?=number_format($ForumTopics)?> <a href="userhistory.php?action=topics&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
                <li id="comm_posts">Forum posts: <?=number_format($User->forumPosts())?> <a href="userhistory.php?action=posts&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php   if ($Override = check_paranoia_here('torrentcomments+')) { ?>
                <li id="comm_torrcomm"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Torrent comments: <?=number_format($Comments['torrents'] ?? 0)?>
<?php       if ($Override = check_paranoia_here('torrentcomments')) { ?>
                    <a href="comments.php?id=<?=$UserID?>" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php       } ?>
                </li>
                <li id="comm_artcomm"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Artist comments: <?=number_format($Comments['artists'] ?? 0)?>
<?php       if ($Override = check_paranoia_here('torrentcomments')) { ?>
                    <a href="comments.php?id=<?=$UserID?>&amp;action=artist" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php       } ?>
                </li>
                <li id="comm_collcomm"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Collage comments: <?=number_format($Comments['collages'] ?? 0)?>
<?php       if ($Override = check_paranoia_here('torrentcomments')) { ?>
                    <a href="comments.php?id=<?=$UserID?>&amp;action=collages" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php       } ?>
                </li>
                <li id="comm_reqcomm"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Request comments: <?=number_format($Comments['requests'] ?? 0)?>
<?php       if ($Override = check_paranoia_here('torrentcomments')) { ?>
                    <a href="comments.php?id=<?=$UserID?>&amp;action=requests" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php        } ?>
                </li>
<?php
        }
    if (($Override = check_paranoia_here('collages+'))) { ?>
                <li id="comm_collstart"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Collages started: <?=number_format($NumCollages)?>
<?php   if ($Override = check_paranoia_here('collages')) { ?>
                    <a href="collages.php?userid=<?=$UserID?>" class="brackets<?=(($Override === 2) ? ' paranoia_override' : '')?>">View</a>
<?php   } ?>
                </li>
<?php
    }
    if (($Override = check_paranoia_here('collagecontribs+'))) { ?>
                <li id="comm_collcontrib"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Collages contributed to: <?php echo number_format($NumCollageContribs); ?>
<?php   if ($Override = check_paranoia_here('collagecontribs')) { ?>
                    <a href="collages.php?userid=<?=$UserID?>&amp;contrib=1" class="brackets<?=(($Override === 2) ? ' paranoia_override' : '')?>">View</a>
<?php   } ?>
                </li>
<?php
    }

    //Let's see if we can view requests because of reasons
    $ViewAll    = check_paranoia_here('requestsvoted_list');
    $ViewCount  = check_paranoia_here('requestsvoted_count');
    $ViewBounty = check_paranoia_here('requestsvoted_bounty');
    $requestCreatedSize = Format::get_size($User->stats()->requestCreatedSize());
    $requestCreatedTotal = number_format($User->stats()->requestCreatedTotal());
    $requestVoteSize = Format::get_size($User->stats()->requestVoteSize());
    $requestVoteTotal = number_format($User->stats()->requestVoteTotal());

    if ($ViewCount && !$ViewBounty && !$ViewAll) { ?>
                <li>Requests created: <?= $requestCreatedTotal ?></li>
                <li>Requests voted: <?= $requestVoteTotal ?></li>
<?php } elseif (!$ViewCount && $ViewBounty && !$ViewAll) { ?>
                <li>Requests created: <?= $requestCreatedSize ?> spent</li>
                <li>Requests voted: <?= $requestVoteSize ?> spent</li>
<?php } elseif ($ViewCount && $ViewBounty && !$ViewAll) { ?>
                <li>Requests created: <?= $requestCreatedTotal  ?> for <?= $requestCreatedSize ?></li>
                <li>Requests voted: <?= $requestVoteTotal ?> for <?= $requestVoteSize ?></li>
<?php } elseif ($ViewAll) { ?>
                <li>
                    <span<?= $ViewCount === 2 ? ' class="paranoia_override"' : '' ?>>Requests created: <?= $requestCreatedTotal ?></span>
                    <span<?= $ViewBounty === 2 ? ' class="paranoia_override"' : '' ?>> for <?= $requestCreatedSize ?></span>
                    <a href="requests.php?type=created&amp;userid=<?= $UserID ?>" class="brackets<?= $ViewAll === 2 ? ' paranoia_override' : '' ?>">View</a>
                </li>
                <li>
                    <span<?= $ViewCount === 2 ? ' class="paranoia_override"' : '' ?>>Requests voted: <?= $requestVoteTotal ?></span>
                    <span<?= $ViewBounty === 2 ? ' class="paranoia_override"' : '' ?>> for <?= $requestVoteSize ?></span>
                    <a href="requests.php?type=voted&amp;userid=<?= $UserID ?>" class="brackets<?= $ViewAll === 2 ? ' paranoia_override' : '' ?>">View</a>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('uploads+')) { ?>
                <li id="comm_upload"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Uploaded: <?=number_format($Uploads)?>
<?php   if ($Override = check_paranoia_here('uploads')) { ?>
                    <a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php       if (check_perms('zip_downloader')) { ?>
                    <a href="torrents.php?action=redownload&amp;type=uploads&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected; be sure to check the size of all torrents before redownloading.');" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">Download</a>
<?php
            }
        }
?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('uniquegroups+')) { ?>
                <li id="comm_uniquegroup"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Unique groups: <?=number_format($UniqueGroups)?>
<?php   if ($Override = check_paranoia_here('uniquegroups')) { ?>
                    <a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>&amp;filter=uniquegroup" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php   } ?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('perfectflacs+')) { ?>
                <li id="comm_perfectflac"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>"Perfect" FLACs: <?=number_format($PerfectFLACs)?>
<?php   if ($Override = check_paranoia_here('perfectflacs')) { ?>
                    <a href="torrents.php?type=uploaded&amp;userid=<?=$UserID?>&amp;filter=perfectflac" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php   } ?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('downloaded')) {
?>
                <li id="comm_downloaded"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Downloaded:
                    <span class="user_commstats" id="user_commstats_downloaded"><a href="#" class="brackets" onclick="commStats(<?=$UserID?>); return false;">Show stats</a></span>
                    <span id="user_commstats_udownloaded"></span>
                    <a href="torrents.php?type=downloaded&amp;userid=<?=$UserID?>" class="brackets">View</a>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('leeching+')) {
?>
                <li id="comm_leeching"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Leeching:
                    <span class="user_commstats" id="user_commstats_leeching"><a href="#" class="brackets" onclick="commStats(<?=$UserID?>); return false;">Show stats</a></span>
<?php   if ($Override = check_paranoia_here('leeching')) { ?>
                    <a href="torrents.php?type=leeching&amp;userid=<?=$UserID?>" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php
        }
        if (!$User->canLeech() && check_perms('users_view_ips')) {
?>
                    <strong>(Disabled)</strong>
<?php   } ?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('snatched+')) { ?>
                <li id="comm_snatched"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Snatched:
                    <span class="user_commstats" id="user_commstats_snatched"><a href="#" class="brackets" onclick="commStats(<?=$UserID?>); return false;">Show stats</a></span>
<?php   if ($Override = check_perms('site_view_torrent_snatchlist', $Class)) { ?>
                    <span id="user_commstats_usnatched"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>></span>
<?php
        }
    }
    if ($Override = check_paranoia_here('snatched')) { ?>
                    <a href="torrents.php?type=snatched&amp;userid=<?=$UserID?>" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php   if (check_perms('zip_downloader')) { ?>
                    <a href="torrents.php?action=redownload&amp;type=snatches&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected, be sure to check the size of all torrents before redownloading.');" class="brackets">Download</a>
<?php   } ?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('seeding+')) {
?>
                <li id="comm_seeding"<?=($Override === 2 ? ' class="paranoia_override"' : '')?>>Seeding:
                    <span class="user_commstats" id="user_commstats_seeding"><a href="#" class="brackets" onclick="commStats(<?=$UserID?>); return false;">Show stats</a></span>
<?php   if ($Override = check_paranoia_here('snatched+')) { ?>
                    <span<?=($Override === 2 ? ' class="paranoia_override"' : '')?> id="user_commstats_seedingperc"></span>
<?php
        }
        if ($Override = check_paranoia_here('seeding')) {
?>
                    <a href="torrents.php?type=seeding&amp;userid=<?=$UserID?>" class="brackets<?=($Override === 2 ? ' paranoia_override' : '')?>">View</a>
<?php       if (check_perms('zip_downloader')) { ?>
                    <a href="torrents.php?action=redownload&amp;type=seeding&amp;userid=<?=$UserID?>" onclick="return confirm('If you no longer have the content, your ratio WILL be affected; be sure to check the size of all torrents before redownloading.');" class="brackets">Download</a>
<?php
            }
        }
?>
                </li>
<?php
    }
    if ($Override = check_paranoia_here('invitedcount')) {
?>
                <li id="comm_invited">Invited: <?=number_format($User->invitedCount())?></li>
<?php
    }
?>
            </ul>
<?php   if (array_key_exists('AutoloadCommStats', $LoggedUser) && $LoggedUser['AutoloadCommStats']) { ?>
            <script type="text/javascript">
                commStats(<?=$UserID?>);
            </script>
<?php   } ?>
        </div>
