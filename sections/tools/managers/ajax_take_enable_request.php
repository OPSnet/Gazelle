<?php

if (!$Viewer->permitted('users_mod')) {
    json_error(403);
}

if (!FEATURE_EMAIL_REENABLE) {
    json_error("This feature is currently disabled.");
}

$Type = $_GET['type'];

if ($Type == "resolve") {
    $IDs = $_GET['ids'];
    $Comment = trim($_GET['comment']);
    $Status = trim($_GET['status']);

    // Error check and set things up
    if ($Status == "Approve" || $Status == "Approve Selected") {
        $Status = AutoEnable::APPROVED;
    } else if ($Status == "Reject" || $Status == "Reject Selected") {
        $Status = AutoEnable::DENIED;
    } else if ($Status == "Discard" || $Status == "Discard Selected") {
        $Status = AutoEnable::DISCARDED;
    } else {
        json_error("Invalid resolution option");
    }

    if (is_array($IDs) && empty($IDs)) {
        json_error("You must select at least one reuqest to use this option");
    } else if (!is_array($IDs) && !is_number($IDs)) {
        json_error("You must select at least 1 request");
    }

    // Handle request
    AutoEnable::handle_requests($IDs, $Status, $Comment);
} else if ($Type == "unresolve") {
    $ID = (int) $_GET['id'];
    AutoEnable::unresolve_request($ID);
} else {
    json_error("Invalid type");
}

echo json_encode(["status" => "success"]);
