<?php
/*
User topic history page
*/

if (!empty($LoggedUser['DisableForums'])) {
    error(403);
}

$UserID = empty($_GET['userid']) ? $LoggedUser['ID'] : $_GET['userid'];
if (!is_number($UserID)) {
    error(0);
}

$PerPage = TOPICS_PER_PAGE;

list($Page, $Limit) = Format::page_limit($PerPage);

$UserInfo = Users::user_info($UserID);
$Username = $UserInfo['Username'];

View::show_header("Threads started by $Username", 'subscriptions,comments,bbcode');

$QueryID = $DB->prepared_query("
SELECT SQL_CALC_FOUND_ROWS
    t.ID,
    t.Title,
    t.CreatedTime,
    t.LastPostTime,
    f.ID,
    f.Name
FROM forums_topics AS t
    LEFT JOIN forums AS f ON f.ID = t.ForumID
WHERE t.AuthorID = ? AND ".Forums::user_forums_sql()."
ORDER BY t.ID DESC
LIMIT {$Limit}", $UserID);


$DB->prepared_query('SELECT FOUND_ROWS()');
list($Results) = $DB->fetch_record();

$DB->set_query_id($QueryID);
?>
<div class="thin">
    <div class="header">
        <h2>Threads started by <a href="user.php?id=<?=$UserID?>"><?=$Username?></a></h2>
    </div>
    <?php
    if (empty($Results)) {
        ?>
        <div class="center">
            No topics
        </div>
        <?php
    } else {
        ?>
        <div class="linkbox">
            <?php
            $Pages = Format::get_pages($Page, $Results, $PerPage, 11);
            echo $Pages;
            ?>
        </div>
        <table class="forum_list border">
            <tr class="colhead">
                <td>Forum</td>
                <td>Topic</td>
                <td>Topic Creation Time</td>
                <td>Last Post Time</td>
            </tr>
        <?php
        $QueryID = $DB->get_query_id();
        while (list($TopicID, $Title, $CreatedTime, $LastPostTime, $ForumID, $ForumTitle) = $DB->fetch_record(1)) {
            ?>
            <tr>
                <td><a href="forums.php?action=viewforum&forumid=<?=$ForumID?>"><?=$ForumTitle?></a></td>
                <td><a href="forums.php?action=viewthread&threadid=<?=$TopicID?>"><?=$Title?></td>
                <td><?=time_diff($CreatedTime)?></td>
                <td><?=time_diff($LastPostTime)?></td>
            </tr>
        <?php     } ?>
        </table>
        <div class="linkbox">
            <?=$Pages?>
        </div>
    <?php } ?>
</div>
<?php View::show_footer(); ?>
