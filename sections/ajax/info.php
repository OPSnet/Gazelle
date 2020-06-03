<?php
//calculate ratio
//returns 0 for DNE and -1 for infinity, because we don't want strings being returned for a numeric value in our java
$Ratio = 0;
if ($LoggedUser['BytesUploaded'] == 0 && $LoggedUser['BytesDownloaded'] == 0) {
    $Ratio = 0;
} elseif ($LoggedUser['BytesDownloaded'] == 0) {
    $Ratio = -1;
} else {
    $Ratio = number_format(max($LoggedUser['BytesUploaded'] / $LoggedUser['BytesDownloaded'] - 0.005, 0), 2); //Subtract .005 to floor to 2 decimals
}

$MyNews = $LoggedUser['LastReadNews'];
$CurrentNews = $Cache->get_value('news_latest_id');
if ($CurrentNews === false) {
    $DB->prepared_query("
        SELECT ID
        FROM news
        ORDER BY Time DESC
        LIMIT 1");
    if ($DB->record_count() === 1) {
        list($CurrentNews) = $DB->next_record();
    } else {
        $CurrentNews = -1;
    }
    $Cache->cache_value('news_latest_id', $CurrentNews, 0);
}

$NewMessages = $Cache->get_value('inbox_new_' . $LoggedUser['ID']);
if ($NewMessages === false) {
    $NewMessages = $DB->scalar("
        SELECT count(*)
        FROM pm_conversations_users
        WHERE UnRead = '1'
            AND InInbox = '1'
            AND UserID = ?
        ", $LoggedUser['ID']
    );
    $Cache->cache_value('inbox_new_' . $LoggedUser['ID'], $NewMessages, 0);
}

if (check_perms('site_torrents_notify')) {
    $NewNotifications = $Cache->get_value('notifications_new_' . $LoggedUser['ID']);
    if ($NewNotifications === false) {
        $NewNotifications = $DB->scalar("
            SELECT count(*)
            FROM users_notify_torrents
            WHERE UnRead = '1'
                AND UserID = ?
            ", $LoggedUser['ID']
        );
        $Cache->cache_value('notifications_new_' . $LoggedUser['ID'], $NewNotifications, 0);
    }
}

// News
$MyNews = $LoggedUser['LastReadNews'];
$CurrentNews = $Cache->get_value('news_latest_id');
if ($CurrentNews === false) {
    $DB->prepared_query("
        SELECT ID
        FROM news
        ORDER BY Time DESC
        LIMIT 1");
    if ($DB->record_count() === 1) {
        list($CurrentNews) = $DB->next_record();
    } else {
        $CurrentNews = -1;
    }
    $Cache->cache_value('news_latest_id', $CurrentNews, 0);
}

// Blog
$MyBlog = $LoggedUser['LastReadBlog'];
$CurrentBlog = $Cache->get_value('blog_latest_id');
if ($CurrentBlog === false) {
    $DB->prepared_query("
        SELECT ID
        FROM blog
        WHERE Important = 1
        ORDER BY Time DESC
        LIMIT 1");
    if ($DB->record_count() === 1) {
        list($CurrentBlog) = $DB->next_record();
    } else {
        $CurrentBlog = -1;
    }
    $Cache->cache_value('blog_latest_id', $CurrentBlog, 0);
}

$subscription = new \Gazelle\Manager\Subscription($LoggedUser['ID']);

json_print("success", [
    'username' => $LoggedUser['Username'],
    'id' => (int)$LoggedUser['ID'],
    'authkey' => $LoggedUser['AuthKey'],
    'passkey' => $LoggedUser['torrent_pass'],
    'notifications' => [
        'messages' => (int)$NewMessages,
        'notifications' => (int)$NewNotifications,
        'newAnnouncement' => $MyNews < $CurrentNews,
        'newBlog' => $MyBlog < $CurrentBlog,
        'newSubscriptions' => $subscription->unread() > 0,
    ],
    'userstats' => [
        'uploaded' => (int)$LoggedUser['BytesUploaded'],
        'downloaded' => (int)$LoggedUser['BytesDownloaded'],
        'ratio' => (float)$Ratio,
        'requiredratio' => (float)$LoggedUser['RequiredRatio'],
        'class' => $ClassLevels[$LoggedUser['Class']]['Name']
    ]
]);
