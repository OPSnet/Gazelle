<?php

use OrpheusNET\Logchecker\Logchecker;

View::show_header('Logchecker');

?>
<div class="linkbox">
    <a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
    <a href="logchecker.php?action=update" class="brackets">Update Uploaded Logs</a>
</div>
<div class="thin">
    <h2 class="center">Orpheus Logchecker</h2>
    <div class="box pad">
        <p>
        Use this page to test our logchecker. You can either upload a log or paste it into the
        text box below. This will then run the file/text against our logchecker displaying to you
        what it would look like on our site. To verify a log's checksum, you will need to upload log file.
        </p>
        <table class="forum_post vertical_margin">
            <tr class="colhead">
                <td colspan="2">Upload file</td>
            </tr>
            <tr>
                <td>
                    <form action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="take_test" />
                        <input type="file" accept="<?=Logchecker::getAcceptValues()?>" name="log" size="40" />
                        <input type="submit" value="Upload log" name="submit" />
                    </form>
                </td>
            </tr>
        </table>
        <table class="forum_post vertical_margin">
            <tr class="colhead">
                <td colspan="2">Paste log (No checksum verification)</td>
            </tr>
            <tr>
                <td>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="take_test" />
                        <textarea rows="20" style="width: 99%" name="pastelog" wrap="soft"></textarea>
                        <br /><br />
                        <input type="submit" value="Upload log" name="submit" />
                    </form>
                </td>
            </tr>
        </table>
    </div>
</div>

<?php
View::show_footer();
