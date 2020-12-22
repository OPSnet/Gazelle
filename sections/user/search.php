<?php
/**********************************************************************
 *>>>>>>>>>>>>>>>>>>>>>>>>>>> User search <<<<<<<<<<<<<<<<<<<<<<<<<<<<*
 **********************************************************************/

if (!empty($_GET['search'])) {

    $_GET['username'] = $_GET['search'];
}

define('USERS_PER_PAGE', 30);

if (isset($_GET['username'])) {

    $_GET['username'] = trim($_GET['username']);
    // form submitted
    $Val = new Gazelle\Util\Validator;
    $Val->setField('username', '1', 'username', 'Please enter a username.');

    if ($Val->validate($_GET)) {
        // Passed validation. Let's rock.
        list($Page, $Limit) = Format::page_limit(USERS_PER_PAGE);
        if ($Page > 10) {
            $Page = 10;
            $Limit = sprintf("%d, %d", ($Page - 1) * USERS_PER_PAGE, USERS_PER_PAGE);
        }
        $DB->prepared_query("
            SELECT
                SQL_CALC_FOUND_ROWS
                ID,
                Username,
                Enabled,
                PermissionID,
                Donor,
                Warned
            FROM users_main AS um
                JOIN users_info AS ui ON ui.UserID = um.ID
            WHERE Username = ?
            ORDER BY Username
            LIMIT $Limit", $_GET['username']);
        $Results = $DB->to_array();
        $DB->query('SELECT FOUND_ROWS()');
        list($NumResults) = $DB->next_record();
        if ($NumResults > 300) {
            $NumResults = 300;
        }
        elseif (intval($NumResults) === 1) {
            list($UserID, $Username, $Enabled, $PermissionID, $Donor, $Warned) = $Results[0];
            header("Location: user.php?id={$UserID}");
        }
    }
}

View::show_header('User search');
?>
<div class="thin">
    <div class="header">
        <h3>Search results</h3>
    </div>
<?php    $Pages = Format::get_pages($Page, $NumResults, USERS_PER_PAGE, 9);
    if ($Pages) { ?>
    <div class="linkbox pager"><?=($Pages)?></div>
<?php } ?>
    <form class="search_form" name="users" action="user.php" method="get">
        <input type="hidden" name="action" value="search" />
        <table class="layout" width="100%">
            <tr>
                <td class="label nobr">Username:</td>
                <td>
                    <input type="text" name="username" size="60" value="<?=display_str($_GET['username'])?>" />
                    &nbsp;
                    <input type="submit" value="Search users" />
                </td>
            </tr>
        </table>
    </form>
    <br />
    <div class="box pad center">
        <table style="width: 400px; margin: 0px auto;">
            <tr class="colhead">
                <td width="50%">Username</td>
                <td>Primary class</td>
            </tr>
<?php
    foreach ($Results as $Result) {
        list($UserID, $Username, $Enabled, $PermissionID, $Donor, $Warned) = $Result;
?>
            <tr>
                <td><?=Users::format_username($UserID, true, true, true, true);?></td>
                <td><?=Users::make_class_string($PermissionID);?></td>
            </tr>
<?php } ?>
        </table>
    </div>
    <div class="linkbox">
    <?=$Pages?>
    </div>
</div>
<?php View::show_footer(); ?>
