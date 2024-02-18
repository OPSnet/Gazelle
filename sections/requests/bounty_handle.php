<?php

authorize();

if (!$Viewer->permitted('site_admin_requests')) {
    error(403);
}

$request = (new Gazelle\Manager\Request())->findById((int)$_POST['id']);
if (is_null($request)) {
    error(404);
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
    if (preg_match('/^(action|check)-(\d+)$/', $k, $match)) {
        if ($match[1] == 'check' && $v) {
            $check[(int)$match[2]] = true;
        } elseif ($match[1] == 'action') {
            if ($v == 'keep') {
                continue;
            }
            if (!in_array($v, ['refund', 'remove'])) {
                error(0);
            }
            $action[(int)$match[2]] = $v;
        } else {
            error(0);
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
$userMan = new Gazelle\Manager\User();
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
    $request->refundBounty($user, $Viewer->username());
}
foreach ($remove as $user) {
    $request->removeBounty($user, $Viewer->username());
}
$request->updateSphinx();

header('Location: ' . $request->location());
