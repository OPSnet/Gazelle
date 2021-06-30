<?php

authorize();

if (!$Viewer->permitted('site_admin_requests')) {
    error(403);
}

$request = (new Gazelle\Manager\Request)->findById((int)$_POST['id']);
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
            $check[$match[2]] = true;
        }
        elseif ($match[1] == 'action') {
            if ($v == 'keep') {
                continue;
            }
            if (!in_array($v, ['refund', 'remove'])) {
                error(0);
            }
            $action[$match[2]] = $v;
        }
        else {
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
$refund = [];
$remove = [];
foreach ($action as $userId => $operation) {
    if ($operation == 'refund') {
        $refund[] = $userId;
    } elseif (isset($check[$userId])) {
        $remove[] = $userId;
    }
}

/* Now we have
 *   $refund = [8]
 *   $remove = [4]
 */
foreach ($refund as $userId) {
    $request->refundBounty($userId, $Viewer->username());
}
foreach ($remove as $userId) {
    $request->removeBounty($userId, $Viewer->username());
}

if ($request || $remove) {
    $Cache->deleteMulti(array_merge(
        ["request_$requestId", "request_votes_$requestId"],
        array_map(fn($x) => "user_stats_$x", $refund)
    ));
    Requests::update_sphinx_requests($request->id());
}

header("Location: requests.php?action=view&id=" . $request->id());
