<?php
if (!check_perms('site_debug') || !check_perms('admin_manage_referrals')) {
    error(403);
}

$ReferralManager = new Gazelle\Manager\Referral($DB, $Cache);
$ReferralAccounts = $ReferralManager->getFullAccounts();

$Cookie = [];
$Params = [];

if (isset($_POST['url'])) {
    authorize();
    $Url = $_POST['url'];
    $Proxy = new \Gazelle\Util\Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);
    $HasResult = true;

    if (isset($_POST['account'])) {
        $Account = $ReferralManager->getFullAccount($_POST['account']);
        $ReferralManager->loginAccount($Account);
        $Cookie = $Account['Cookie'];
    } else {
        $Cookie = $_POST['cookie'];
        if (strlen($Cookie) < 2) {
            $Cookie = [];
        }
        $Cookie = json_decode($Cookie);
        if (json_last_error() != JSON_ERROR_NONE) {
            $Cookie = [];
        }
    }

    $Params = $_POST['params'];
    $Params = json_decode($Params);
    if (json_last_error() != JSON_ERROR_NONE) {
        $Params = [];
    }

    $Post = isset($_POST['post']);

    $Request = ['Url' => $Url, 'Params' => $Params, 'Cookie' => $Cookie, 'Post' => $Post];
    $Response = $Proxy->fetch($Url, $Params, $Cookie, $Post);
}

View::show_header("Referral Sandbox");
?>
<style type="text/css">
div#preview {display: none;}
</style>

<div class="header">
    <h2>Referral Sandbox</h2>
</div>
<?php if ($HasResult) { ?>
<div class="thin box pad">
    <div class="thin">
        <h3>Request</h3>
        <div class="box pad">
<?php var_dump($Request); ?>
        </div>
    </div>
    <div class="thin">
        <h3>Response</h3>
        <div class="box pad">
            <a onclick="toggle_display('preview')" href="javascript:void(0)">Toggle Preview</a><br />
            <div id="preview">
                <iframe style="width: 100%; height: 600px;" srcdoc="<?=str_replace('"', '&quot;', str_replace('&', '&amp;', $Response['response']))?>"></iframe>
            </div><br />
            <div>
<?php var_dump($Response) ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<div class="thin box pad">
    <h3>Manual</h3>
    <form class="send_form" action="" method="post">
        <input type="hidden" name="action" value="referral_sandbox" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table class="layout">
            <tbody>
                <tr>
                    <td class="label">
                        <label for="url">URL</label>
                    </td>
                    <td>
                        <input style="width: 98%;" type="text" name="url" value="<?=$Url?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="cookie">Cookies</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="cookie" cols="90" rows="8"><?=json_encode($Cookie)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="params">Parameters</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="params" cols="90" rows="8"><?=json_encode($Params)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="post">POST</label>
                    </td>
                    <td>
                        <input type="checkbox" name="post"<?=$Post ? ' checked="checked"' : ""?> />
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="submit" value="Submit" />
    </form>
</div>
<div class="thin box pad">
<?php if ($ReferralManager->readOnly) { ?>
    <p>
        <strong class="important_text">DB key not loaded - accounts disabled</strong>
    </p>
<?php } else if (empty($ReferralAccounts)) { ?>
    <h3>Auto</h3>
    <p>No referral accounts found.</p>
<?php } else { ?>
    <h3>Auto</h3>
    <form class="send_form" action="" method="post">
        <input type="hidden" name="action" value="referral_sandbox" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <table class="layout">
            <tbody>
                <tr>
                    <td class="label">
                        <label for="url">URL</label>
                    </td>
                    <td>
                        <input style="width: 98%;" type="text" name="url" value="<?=$Url?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="account">Account</label>
                    </td>
                    <td>
<?php
    foreach ($ReferralAccounts as $Account) {
        $ID = $Account["ID"];
?>
                        <label for="<?=$ID?>"><?=$Account["Site"]?></label>
                        <input id="<?=$ID?>" type="radio" name="account" value="<?=$ID?>" />
<?php } ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="params">Parameters</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="params" cols="90" rows="8"><?=json_encode($Params)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="post">POST</label>
                    </td>
                    <td>
                        <input type="checkbox" name="post"<?=$Post ? ' checked="checked"' : ""?> />
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="submit" value="Submit" />
    </form>
<?php } ?>
</div>
<?php
View::show_footer();
?>
