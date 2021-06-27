<?php
$vote = Gazelle\Vote($Viewer->id());
$userVote = $vote->userVotes();
$GroupVote = $vote->groupVote($groupId);

$Score      = $GroupVote['Score'];
$TotalVotes = $GroupVote['Total'];
$UpVotes    = $GroupVote['Ups'];
$DownVotes  = $TotalVotes - $UpVotes;

$Voted = isset($UserVotes[$GroupID]) ? $UserVotes[$GroupID]['Type'] : false;
$Percentage = $TotalVotes > 0 ? number_format($UpVotes / $TotalVotes * 100, 1) : '&mdaash;';
?>
<div class="box" id="votes">
    <div class="head"><strong>Album Votes</strong></div>
    <div class="album_votes body">
        <span class="favoritecount tooltip" title="<?=$UpVotes . ($UpVotes == 1 ? ' upvote' : ' upvotes')?>"><span id="upvotes"><?=number_format($UpVotes)?></span> <span class="vote_album_up">&and;</span></span>
        &nbsp; &nbsp;
        <span class="favoritecount tooltip" title="<?=$DownVotes . ($DownVotes == 1 ? ' downvote' : ' downvotes')?>"><span id="downvotes"><?=number_format($DownVotes)?></span> <span class="vote_album_down">&or;</span></span>
        &nbsp; &nbsp;
        <span class="favoritecount" id="totalvotes"><?=number_format($TotalVotes)?></span> Total
        <br /><br />
        <span class="tooltip_interactive" title="&lt;span style=&quot;font-weight: bold;&quot;&gt;Score: <?=number_format($Score * 100, 4)?>&lt;/span&gt;&lt;br /&gt;&lt;br /&gt;This is the lower bound of the binomial confidence interval &lt;a href=&quot;wiki.php?action=article&amp;id=108&quot;&gt;described here&lt;/a&gt;, multiplied by 100." data-title-plain="Score: <?=number_format($Score * 100, 4)?>. This is the lower bound of the binomial confidence interval described in the Favorite Album Votes wiki article, multiplied by 100.">Score: <span class="favoritecount"><?=number_format($Score * 100, 1)?></span></span>
        &nbsp; | &nbsp;
        <span class="favoritecount"><?=$Percentage?>%</span> positive
        <br /><br />
        <span id="upvoted"<?=(($Voted != 'Up') ? ' class="hidden"' : '')?>>You have upvoted.<br /><br /></span>
        <span id="downvoted"<?=(($Voted != 'Down') ? ' class="hidden"' : '')?>>You have downvoted.<br /><br /></span>
<?php    if (check_perms('site_album_votes')) { ?>
        <span<?=($Voted ? ' class="hidden"' : '')?> id="vote_message"><a href="#" class="brackets upvote" onclick="UpVoteGroup(<?=$GroupID?>, '<?= $Viewer->auth() ?>'); return false;">Upvote</a> - <a href="#" class="brackets downvote" onclick="DownVoteGroup(<?=$GroupID?>, '<?= $Viewer->auth() ?>'); return false;">Downvote</a></span>
<?php    } ?>
        <span<?=($Voted ? '' : ' class="hidden"')?> id="unvote_message">
            Changed your mind?
            <br />
            <a href="#" onclick="UnvoteGroup(<?=$GroupID?>, '<?= $Viewer->auth() ?>'); return false;" class="brackets">Clear your vote</a>
        </span>
    </div>
</div>
