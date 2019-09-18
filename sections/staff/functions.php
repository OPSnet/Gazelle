<?
/**
 * Generate a table row for a staff member on staff.php
 *
 * @param String $Row used for alternating row colors
 * @param $ID the user ID of the staff member
 * @param $Paranoia the user's paranoia
 * @param $Class the user class
 * @param $LastAccess datetime the user last browsed the site
 * @param String $Remark the "Staff remark" or FLS' "Support for" text
 * @param String $HiddenBy the text that is displayed when a staff member's
 *                         paranoia hides their LastAccess time
 * @return string $Row
 */
function make_staff_row($Row, $ID, $Paranoia, $Class, $LastAccess, $Remark = '', $HiddenBy = 'Hidden by user') {
    $Row = $Row === 'a' ? 'b' : 'a';

    echo "\t\t\t<tr class=\"row$Row\">
                <td class=\"nobr\">
                    " . Users::format_username($ID, false, false, false) . "
                </td>
                <td class=\"nobr\">
                    "; //used for proper indentation of HTML
                    if (check_paranoia('lastseen', $Paranoia, $Class)) {
                        echo time_diff($LastAccess);
                    } else {
                        echo "$HiddenBy";
                    }
    echo "\n\t\t\t\t</td>
                <td class=\"nobr\">"
                    . Text::full_format($Remark) .
                "</td>
            </tr>\n"; // the "\n" is needed for pretty HTML
    // the foreach loop that calls this function needs to know the new value of $Row
    return $Row;
}

function get_fls() {
    global $Cache, $DB;
    static $FLS;
    if (is_array($FLS)) {
        return $FLS;
    }
    if (($FLS = $Cache->get_value('fls')) === false) {
        $DB->prepared_query('
            SELECT
                m.ID,
                p.Level,
                m.Username,
                m.Paranoia,
                m.LastAccess,
                i.SupportFor
            FROM users_info AS i
                JOIN users_main AS m ON m.ID = i.UserID
                JOIN permissions AS p ON p.ID = m.PermissionID
                JOIN users_levels AS l ON l.UserID = i.UserID
            WHERE l.PermissionID = ?
            ORDER BY m.Username', FLS_TEAM);
        $FLS = $DB->to_array(false, MYSQLI_BOTH, array(3, 'Paranoia'));
        $Cache->cache_value('fls', $FLS, 180);
    }
    return $FLS;
}

function get_staff() {
    global $Cache, $DB;
    static $Staff;
    if (is_array($Staff)) {
        return $Staff;
    }

    if (($Staff = $Cache->get_value('staff')) === false) {
        $DB->prepared_query("
        SELECT
            m.ID,
            p.ID as LevelID,
            p.Level,
            p.Name,
            IFNULL(sg.Name, '') AS StaffGroup,
            m.Username,
            m.Paranoia,
            m.LastAccess,
            i.SupportFor
        FROM users_main AS m
            JOIN users_info AS i ON m.ID = i.UserID
            JOIN permissions AS p ON p.ID = m.PermissionID
            INNER JOIN staff_groups AS sg ON sg.ID = p.StaffGroup
        WHERE p.DisplayStaff = '1' AND Secondary = 0
        ORDER BY p.Level, m.Username");
        $TmpStaff = $DB->to_array(false, MYSQLI_BOTH, array(6, 'Paranoia'));
        $DB->prepared_query("
            SELECT Name
            FROM staff_groups
            ORDER BY Sort");
        $Groups = $DB->collect('Name');
        array_unshift($Groups, 'Staff');
        $Staff = [];
        foreach ($Groups as $g) {
            $Staff[$g] = [];
        }
        foreach ($TmpStaff as $Class) {
            $Staff[$Class['StaffGroup']][] = $Class;
        }
        $Cache->cache_value('staff', $Staff, 180);
    }
    return $Staff;
}

function get_support() {
    return array(
        get_fls(),
        get_staff()
    );
}

function printSectionDiv($ClassName) {
?>
        </div><br />
        <div class='box pad' style='padding: 10px 10px 10px 10px;'>
        <h2 style='text-align: left;'><?=$ClassName?></h2>
<?
}
