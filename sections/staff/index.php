<?php
enforce_login();
View::show_header('Staff');

include(SERVER_ROOT.'/sections/staff/functions.php');

$SupportStaff = get_support();

list($FrontLineSupport, $Staff) = $SupportStaff;
?>

<?php
if (check_perms('admin_manage_applicants')) { ?>
<div class="linkbox">
    <a href="apply.php">Role applications</a>
</div>
<?php
} ?>

<div class="thin">
    <div class="header">
        <h2><?=SITE_NAME?> Staff</h2>
    </div>
    <div class="box pad" style="padding: 0px 10px 10px 10px;">
        <br />
        <h3>Contact Staff</h3>
        <div id="below_box">
            <p>If you are looking for help with a general question, we appreciate it if you would only message through the staff inbox, where we can all help you.</p>
            <p>You can do that by <strong><a href="#" onclick="$('#compose').gtoggle(); return false;">sending a message to the Staff Inbox</a></strong>.</p>
            <p>If you'd like to join our staff, please feel free to <strong><a href="apply.php">apply</a></strong>!</p>
        </div>
    </div>
    <div class="box pad" style="padding: 0px 10px 10px 10px;">
        <?php View::parse('generic/reply/staffpm.php', ['Hidden' => true]); ?>
        <br />
        <h2 style="text-align: left;">Community Help</h2>
        <h3 style="font-size: 17px;" id="fls"><i>First-Line Support</i></h3>
        <p><strong>These users are not official staff members.</strong> They are users who have volunteered their time to help people in need. Please treat them with respect, and read <a href="wiki.php?action=article&amp;id=52">this</a> before contacting them.</p><br />
        <table class="staff" width="100%">
            <tr class="colhead">
                <td style="width: 130px;">Username</td>
                <td style="width: 130px;">Last seen</td>
                <td><strong>Support for</strong></td>
            </tr>
<?php
    $Row = 'a';
    foreach ($FrontLineSupport as $Support) {
        list($ID, $Class, $Username, $Paranoia, $LastAccess, $SupportFor) = $Support;

        $Row = make_staff_row($Row, $ID, $Paranoia, $Class, $LastAccess, $SupportFor);

    } ?>
        </table>
    </div>
    <br />
<?php

    foreach ($Staff as $SectionName => $StaffSection) {
        if (count($StaffSection) === 0) {
            continue;
        }
?>
    <div class="box pad" style="padding: 0px 10px 10px 10px;">
        <h2 style='text-align: left;'><?=$SectionName?></h2>
<?php
        $CurClass = 0;
        $CloseTable = false;
        foreach ($StaffSection as $StaffMember) {
            list($ID, $ClassID, $Class, $ClassName, $StaffGroup, $Username, $Paranoia, $LastAccess, $Remark) = $StaffMember;
            if ($Class != $CurClass) { // Start new class of staff members
                $Row = 'a';
                if ($CloseTable) {
                    $CloseTable = false;
                    // the "\t" and "\n" are used here to make the HTML look pretty
                    echo "\t\t</table>\n\t\t<br />\n";
                }
                $CurClass = $Class;
                $CloseTable = true;

                $HTMLID = str_replace(' ', '_', strtolower($ClassName));
                echo "\t\t<h3 style=\"font-size: 17px;\" id=\"$HTMLID\"><i>".$ClassName."s</i></h3>\n";
?>
        <table class="staff" width="100%">
            <tr class="colhead">
                <td style="width: 130px;">Username</td>
                <td style="width: 130px;">Last seen</td>
                <td><strong>Remark</strong></td>
            </tr>
<?php
        } // End new class header

        $HiddenBy = 'Hidden by staff member';

        // Display staff members for this class
        $Row = make_staff_row($Row, $ID, $Paranoia, $Class, $LastAccess, $Remark, $HiddenBy);

    } ?>
        </table>

    </div>
    <br />
    <?php } ?>
</div>
<?php
View::show_footer();
?>
