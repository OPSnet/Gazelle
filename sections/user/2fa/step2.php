<?php
View::show_header('Two-factor Authentication');
?>

<div class="box pad">
    <p>Please note that if you lose your 2FA key and all of your backup keys, the <?= SITE_NAME ?> staff cannot help you
        retrieve your account. Ensure you keep your backup keys in a safe place.</p>
</div>

<form method="post">
    <table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border">
        <thead>
            <tr class="colhead_dark">
                <td colspan="2">
                    <strong>Please enter your two-factor authentication key given to you by your App.</strong>
                </td>
            </tr>
        </thead>
        
        <tbody>
            <tr>
                <td class="label tooltip_interactive"
                    title="If all went to plan last step, your authentication app should've given you a code. Please enter that here."
                    data-title-plain="If all went to plan last step, your authentication app should've given you a code. Please enter that here.">
                    <label for="2fa"><strong>Authentication Key</strong></label>
                </td>
                
                <td>
                    <input type="text" size="50" name="2fa" id="2fa"/>
                </td>
            </tr>
        
            <tr>
                <td colspan="2">
                    <input type="submit">
                </td>
            </tr>
        </tbody>
    </table>
</form>

<?php View::show_footer(); ?>
