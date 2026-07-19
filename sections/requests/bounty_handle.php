<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('site_admin_requests')) {
    Error403::error();
}

$request = new Manager\Request()->findById((int)$_POST['id']);
if (is_null($request)) {
    Error404::error();
}

$action = [];
$check  = [];
foreach ($_POST as $k => $v) {
    /* Look for refund, remove and check operations.
     * 'action-10' => 'keep'
     * 'action-8'  => 'refund'
     * 'action-4'  => 'remove'
     * 'check-4'   => 'on'
     * 'action-2'  => 'keep'
     */
    if (preg_match('/^(action|check)-(\d+)$/', (string)$k, $match)) {
        if ($match[1] == 'check' && $v) {
            $check[(int)$match[2]] = true;
        } elseif ($match[1] == 'action') {
            if ($v == 'keep') {
                continue;
            }
            if (!in_array($v, ['refund', 'remove'])) {
                Error400::error('Unknown bounty action');
            }
            $action[(int)$match[2]] = $v;
        } else {
            Error400::error('Unknown bounty edit requested');
        }
    }
}

/* Now we have
 *   $action:
 *      8 => 'refund'
 *      4 => 'remove'
 *    $check:
 *      4 => true
 */
$userMan = new Manager\User();
$refund = [];
$remove = [];
foreach ($action as $userId => $operation) {
    $user = $userMan->findById($userId);
    if ($user) {
        if ($operation == 'refund') {
            $refund[] = $user;
        } elseif (isset($check[$userId])) {
            $remove[] = $user;
        }
    }
}

/* Now we have
 *   $refund = [8]
 *   $remove = [4]
 */
foreach ($refund as $user) {
    $request->refundBounty($user, $Viewer);
}
foreach ($remove as $user) {
    $request->removeBounty($user, $Viewer);
}

header('Location: ' . $request->location());
