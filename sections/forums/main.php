<?php
$LastRead = Forums::get_last_read($Forums);
View::show_header('Forums');
?>
<div class="thin">
    <h2>Forums</h2>
    <div class="forum_list">
<?php

$Row = 'a';
$LastCategoryID = 0;
$OpenTable = false;
foreach ($Forums as $Forum) {
    list($ForumID, $CategoryID, $ForumName, $ForumDescription, $MinRead, $MinWrite, $MinCreate, $NumTopics, $NumPosts, $LastPostID, $LastAuthorID, $LastTopicID, $LastTime, $SpecificRules, $LastTopic, $Locked, $Sticky) = array_values($Forum);
    if (!Forums::check_forumperm($ForumID)) {
        continue;
    }
    if ($ForumID == DONOR_FORUM) {
        $descriptions = [
            "I want only two houses, rather than seven... I feel like letting go of things",
            "A billion here, a billion there, sooner or later it adds up to real money.",
            "I've cut back, because I'm buying a house in the West Village.",
            "Some girls are just born with glitter in their veins.",
            "I get half a million just to show up at parties. My life is, like, really, really fun.",
            "Some people change when they think they're a star or something",
            "I'd rather not talk about money. It’s kind of gross.",
            "I have not been to my house in Bermuda for two or three years, and the same goes for my house in Portofino. How long do I have to keep leading this life of sacrifice?",
            "When I see someone who is making anywhere from $300,000 to $750,000 a year, that's middle class.",
            "Money doesn't make you happy. I now have $50 million but I was just as happy when I had $48 million.",
            "I'd rather smoke crack than eat cheese from a tin.",
            "I am who I am. I can’t pretend to be somebody who makes $25,000 a year.",
            "A girl never knows when she might need a couple of diamonds at ten 'o' clock in the morning.",
            "I wouldn't run for president. I wouldn't want to move to a smaller house.",
            "I have the stardom glow.",
            "What's Walmart? Do they like, sell wall stuff?",
            "Whenever I watch TV and see those poor starving kids all over the world, I can't help but cry. I mean I'd love to be skinny like that, but not with all those flies and death and stuff.",
            "Too much money ain't enough money.",
            "What's a soup kitchen?",
            "I work very hard and I’m worth every cent!",
            "To all my Barbies out there who date Benjamin Franklin, George Washington, Abraham Lincoln, you'll be better off in life. Get that money."
        ];
        $ForumDescription = $descriptions[rand(0, count($descriptions) - 1)];
    }
    $Tooltip = $ForumID == DONOR_FORUM ? 'tooltip_gold' : 'tooltip';
    $Row = $Row === 'a' ? 'b' : 'a';
    $ForumDescription = display_str($ForumDescription);

    if ($CategoryID != $LastCategoryID) {
        $Row = 'b';
        $LastCategoryID = $CategoryID;
        if ($OpenTable) { ?>
    </table>
<?php   } ?>
<h3><?=$ForumCats[$CategoryID]?></h3>
    <table class="forum_index m_table">
        <tr class="colhead">
            <td style="width: 2%;"></td>
            <td class="m_th_left" style="width: 25%;">Forum</td>
            <td>Last Post</td>
            <td class="m_th_right" style="width: 7%;">Topics</td>
            <td class="m_th_right" style="width: 7%;">Posts</td>
        </tr>
<?php
        $OpenTable = true;
    }

    $Read = Forums::is_unread($Locked, $Sticky, $LastPostID, $LastRead, $LastTopicID, $LastTime) ? 'unread' : 'read';
/* Removed per request, as distracting
    if ($Locked) {
        $Read .= '_locked';
    }
    if ($Sticky) {
        $Read .= '_sticky';
    }
*/
?>
    <tr class="row<?=$Row?>">
        <td class="td_read <?=$Read?> <?=$Tooltip?>" title="<?=ucfirst($Read)?>"></td>
        <td class="td_forum">
            <h4 class="min_padding">
                <a class="<?=$Tooltip?>" href="forums.php?action=viewforum&amp;forumid=<?=$ForumID?>" title="<?=display_str($ForumDescription)?>"><?=display_str($ForumName)?></a>
            </h4>
        </td>
<?php
    if ($NumPosts == 0) { ?>
        <td class="td_latest">
            There are no topics here.<?=(($MinCreate <= $LoggedUser['Class']) ? ' <a href="forums.php?action=new&amp;forumid='.$ForumID.'">Create one!</a>' : '')?>
        </td>
        <td class="td_topic_count number_column m_td_right">0</td>
        <td class="td_post_count number_column m_td_right">0</td>
<?php
    } else { ?>
        <td class="td_latest">
            <span style="float: left;" class="last_topic">
                <a href="forums.php?action=viewthread&amp;threadid=<?=$LastTopicID?>" class="tooltip" data-title-plain="<?=display_str($LastTopic)?>" <?=((strlen($LastTopic) > 50) ? "title='".display_str($LastTopic)."'" : "")?>><?=display_str(Format::cut_string($LastTopic, 50, 1))?></a>
            </span>
<?php
    if (!empty($LastRead[$LastTopicID])) { ?>
            <span style="float: left;" class="<?=$Tooltip?> last_read" title="Jump to last read">
                <a href="forums.php?action=viewthread&amp;threadid=<?=$LastTopicID?>&amp;page=<?=$LastRead[$LastTopicID]['Page']?>#post<?=$LastRead[$LastTopicID]['PostID']?>"></a>
            </span>
<?php
    } ?>
            <span style="float: right;" class="last_poster">by <?=Users::format_username($LastAuthorID, false, false, false)?> <?=time_diff($LastTime, 1)?></span>
        </td>
        <td class="td_topic_count number_column m_td_right"><?=number_format($NumTopics)?></td>
        <td class="td_post_count number_column m_td_right"><?=number_format($NumPosts)?></td>
<?php
} ?>
    </tr>
<?php
} ?>
    </table>
    </div>
    <div class="linkbox"><a href="forums.php?action=catchup&amp;forumid=all&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Catch up</a></div>
</div>

<?php View::show_footer(); ?>
