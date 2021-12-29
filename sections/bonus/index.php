<?php

if ($Viewer->disableBonusPoints()) {
    error('Your points have been disabled.');
}

const DEFAULT_PAGE = 'store.php';

switch ($_GET['action'] ?? '') {
    case 'purchase':
        /* handle validity and cost as early as possible */
        if (isset($_REQUEST['label']) && preg_match('/^[a-z]{1,15}(-\w{1,15}){0,4}/', $_REQUEST['label'])) {
            $viewerBonus = new \Gazelle\Bonus($Viewer);
            $Label = $_REQUEST['label'];
            $Item = $viewerBonus->getItem($Label);
            if ($Item) {
                $Price = $viewerBonus->getEffectivePrice($Label);
                if ($Price > $Viewer->bonusPointsTotal()) {
                    error('You cannot afford this item.');
                }
                switch($Label)  {
                    case 'token-1': case 'token-2': case 'token-3': case 'token-4':
                        require_once('tokens.php');
                        break;
                    case 'other-1': case 'other-2': case 'other-3': case 'other-4':
                        require_once('token_other.php');
                        break;
                    case 'invite':
                        require_once('invite.php');
                        break;
                    case 'title-bb-y':
                    case 'title-bb-n':
                    case 'title-off':
                        require_once('title.php');
                        break;
                    case 'collage-1':
                    case 'seedbox':
                        require_once('purchase.php');
                        break;
                    default:
                        require_once(DEFAULT_PAGE);
                        break;
                }
            }
            else {
                require_once(DEFAULT_PAGE);
                break;
            }
        }
        break;
    case 'bprates':
        require_once('bprates.php');
        break;
    case 'title':
        require_once('title.php');
        break;
    case 'history':
        require_once('history.php');
        break;
    case 'cacheflush':
        (new \Gazelle\Manager\Bonus)->flushPriceCache();
        header("Location: bonus.php");
        exit;
    case 'donate':
    default:
        require_once(DEFAULT_PAGE);
        break;
}
