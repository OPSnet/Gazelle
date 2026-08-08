<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    json_error(403);
}

if (!FEATURE_EMAIL_REENABLE) {
    json_error("This feature is currently deactivated.");
}

$enableMan = new Manager\AutoEnable();
$idList = array_map(intval(...), $_GET['ids'] ?? []);
if (empty($idList)) {
    json_error("You must select at least one request to resolve");
}

switch ($_GET['type'] ?? '') {
    case "resolve":
        $status = match (trim($_GET['status' ?? ''])) {
            "Approve", "Approve Selected" => Manager\AutoEnable::APPROVED,
            "Discard", "Discard Selected" => Manager\AutoEnable::DISCARDED,
            "Reject", "Reject Selected"   => Manager\AutoEnable::DENIED,
            default                       => json_error("Invalid resolution option"),
        };
        $enableMan->resolveList($Viewer, $idList, $status, trim($_GET['comment'] ?? ''));
        break;

    case "unresolve":
        $enableRequest = $enableMan->findById($idList[0]);
        if ($enableRequest?->isDiscarded()) {
            $enableRequest->unresolve($Viewer);
        } else {
            json_error(404);
        }
        break;

    default:
        json_error("Invalid type");
}

echo json_encode(["status" => "success"]);
