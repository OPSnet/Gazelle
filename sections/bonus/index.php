<?php
/** @phpstan-var \Gazelle\User $Viewer */

if ($Viewer->disableBonusPoints()) {
    error('Your bonus points have been deactivated.');
}

const DEFAULT_PAGE = 'store.php';

switch ($_GET['action'] ?? '') {
    case 'purchase':
        /* handle validity and cost as early as possible */
        if (preg_match('/^[a-z]{1,15}(-\w{1,15}){0,4}/', $_REQUEST['label'] ?? '')) {
            $viewerBonus = new \Gazelle\User\Bonus($Viewer);
            $Label = $_REQUEST['label'];
            $Item = $viewerBonus->item($Label);
            if (!$Item) {
                include_once DEFAULT_PAGE;
                break;
            }
            $Price = $viewerBonus->effectivePrice($Label);
            if ($Price > $Viewer->bonusPointsTotal()) {
                error('You cannot afford this item.');
            }
            include_once match ($Label) {
                'invite'                                   => 'invite.php',
                'collage-1', 'seedbox', 'file-count'       => 'purchase.php',
                'title-bb-y', 'title-bb-n', 'title-off'    => 'title.php',
                'token-1', 'token-2', 'token-3', 'token-4' => 'tokens.php',
                'other-1', 'other-2', 'other-3', 'other-4' => 'token_other.php',
                default                                    => DEFAULT_PAGE,
            };
        }
        break;
    case 'bprates':
        include_once 'bprates.php';
        break;
    case 'title':
        include_once 'title.php';
        break;
    case 'history':
        include_once 'history.php';
        break;
    case 'cacheflush':
        (new \Gazelle\Manager\Bonus())->flushPriceCache();
        header("Location: bonus.php");
        exit;
    case 'donate':
    default:
        include_once DEFAULT_PAGE;
        break;
}
