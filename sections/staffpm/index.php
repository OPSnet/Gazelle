<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

require __DIR__ . '/' . match ($_REQUEST['action'] ?? null) {
    'assign'          => 'assign.php',
    'viewconv'        => 'viewconv.php',
    'takepost'        => 'viewconv_handle.php',
    'unresolve'       => 'unresolve.php',
    'multiresolve'    => 'multiresolve.php',
    'responses'       => 'common_responses.php',
    'delete_response' => 'ajax_delete_response.php',
    'edit_response'   => 'ajax_edit_response.php',
    'get_response'    => 'ajax_get_response.php',
    'get_post'        => 'get_post.php',
    'scoreboard'      => 'scoreboard.php',
    'userinbox'       => 'user_inbox.php',
    default           => $Viewer->isStaffPMReader() ? 'staff_inbox.php' : 'user_inbox.php',
};
