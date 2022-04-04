<?php

$toc = (new Gazelle\Manager\Forum())->tableOfContentsMain();

View::show_header('Forums');
?>
<div class="thin">
    <h2>Forums</h2>
    <div class="forum_list">
<?php
foreach ($toc as $category => $forumList) {
    $seen = 0;
    foreach ($forumList as $f) {
        $forum = new Gazelle\Forum($f['ID']);
        if (!$Viewer->readAccess($forum)) {
            continue;
        }
        if ($f['ID'] == DONOR_FORUM) {
            $f['Description'] = donorForumDescription();
        }
        $userLastRead = $forum->userLastRead($Viewer->id(), $Viewer->postsPerPage());
        if (isset($userLastRead[$f['LastPostTopicID']])) {
            $lastReadPage = (int)$userLastRead[$f['LastPostTopicID']]['Page'];
            $lastReadPost = $userLastRead[$f['LastPostTopicID']]['PostID'];
            $catchup = $userLastRead[$f['LastPostTopicID']]['PostID'] >= $f['LastPostID']
                || $Viewer->forumCatchupEpoch() >= strtotime($f['LastPostTime']);
            $isRead = true;
        } else {
            $lastReadPage = null;
            $lastReadPost = null;
            $catchup = $f['LastPostTime'] ? $Viewer->forumCatchupEpoch() >= strtotime($f['LastPostTime']) : false;
            $isRead = false;
        }

        $iconClass = (($f['IsLocked'] && !$f['IsSticky']) || $catchup ? 'read' : 'unread')
            . ($f['IsLocked'] ? '_locked' : '')
            . ($f['IsSticky'] ? '_sticky' : '');

        echo $Twig->render('forum/main.twig', [
            'creator'        => $f['MinClassCreate'] <= $Viewer->classLevel(),
            'category'       => $category,
            'category_id'    => $f['categoryId'],
            'cut_title'      => shortenString($f['Title'] ?? '', 50, true),
            'description'    => $f['Description'],
            'forum_id'       => $f['ID'],
            'icon_class'     => $iconClass,
            'id'             => $f['LastPostTopicID'],
            'is_read'        => $isRead,
            'has_poll'       => $f['has_poll'],
            'last_post_time' => $f['LastPostTime'],
            'last_post_user' => $f['LastPostAuthorID'],
            'last_read_page' => $lastReadPage,
            'last_read_post' => $lastReadPost,
            'name'           => $f['Name'],
            'num_posts'      => $f['NumPosts'],
            'num_topics'     => $f['NumTopics'],
            'seen'           => ++$seen, // $seen == 1 implies <table> needs to be emitted
            'threads'        => $f['NumPosts'] > 0,
            'title'          => $f['Title'],
            'tooltip'        => $f['ID'] == DONOR_FORUM ? 'tooltip_gold' : 'tooltip',
        ]);
    }
    /* close the <table> opened in first call to render() above */
    if ($seen) { ?>
        </table>
<?php
    }
} /* foreach */
?>
    </div>
    <div class="linkbox"><a href="forums.php?action=catchup&amp;forumid=all&amp;auth=<?= $Viewer->auth() ?>" class="brackets">Catch up</a></div>
</div>
<?php
View::show_footer();

function donorForumDescription() {
    $description = [
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
        "To all my Barbies out there who date Benjamin Franklin, George Washington, Abraham Lincoln, you'll be better off in life. Get that money.",
    ];
    return $description[rand(0, count($description) - 1)];
};
