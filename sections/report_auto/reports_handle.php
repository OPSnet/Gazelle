<?php
/** @phpstan-var \Gazelle\User $Viewer */

header('Content-Type: application/json; charset=utf-8');

if (!$Viewer->permitted('users_auto_reports')) {
    json_error('permission denied');
}
authorize(true);

$actionId  = (int)($_REQUEST['id'] ?? 0);
$reportMan = new \Gazelle\Manager\ReportAuto();

if ($_REQUEST['action'] === 'claim_all') {
    $reportMan->claimAll($Viewer, $actionId, empty($_REQUEST['type']) ? null : (int)$_REQUEST['type']);
} elseif ($_REQUEST['action'] === 'resolve_all') {
    $reportMan->resolveAll($Viewer, $actionId, empty($_REQUEST['type']) ? null : (int)$_REQUEST['type']);
} elseif ($_REQUEST['action'] === 'delete_comment') {
    if (!$reportMan->deleteComment($actionId, $Viewer)) {
        json_error('failed to delete comment');
    }
} elseif ($_REQUEST['action'] === 'edit_comment') {
    if (empty($_REQUEST['comment'])) {
        json_error("comment cannot be empty");
    }
    if (!$reportMan->editComment($actionId, $Viewer, $_REQUEST['comment'])) {
        json_error('failed to edit comment');
    }
} else {
    if (!$report = $reportMan->findById($actionId)) {
        json_error('report not found');
    }
    switch ($_REQUEST['action']) {
        case 'claim':
            $report->claim($Viewer);
            break;
        case 'unclaim':
            if ($report->ownerId() !== $Viewer->id()) {
                json_error('can only unclaim own reports');
            }
            $report->claim(null);
            break;
        case 'resolve':
            $report->resolve($Viewer);
            break;
        case 'unresolve':
            $report->unresolve($Viewer);
            break;
        case 'add_comment':
            if (empty($_REQUEST['comment'])) {
                json_error("comment cannot be empty");
            }
            $report->addComment($Viewer, $_REQUEST['comment']);
            break;
        default:
            json_error('unknown action');
    }
}

json_print('success');
