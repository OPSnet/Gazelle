<?php

$IS_STAFF = $Viewer->permitted('admin_manage_applicants'); /* important for viewing the full story and full applicant list */
$Resolved = (isset($_GET['status']) && $_GET['status'] === 'resolved');

if (isset($_POST['id']) && is_number($_POST['id'])) {
    authorize();
    $ID = (int)$_POST['id'];
    $app = new Gazelle\Applicant($ID);
    if (!$IS_STAFF && $app->userId() != $Viewer->id()) {
        error(403);
    }
    $remove = array_filter($_POST, fn ($x) => preg_match('/^note-delete-\d+$/', $x), ARRAY_FILTER_USE_KEY);
    if (is_array($remove) && count($remove) == 1) {
            $app->removeNote(
                trim(array_keys($remove)[0], 'note-delete-')
            );
    } elseif (isset($_POST['resolve'])) {
        if ($_POST['resolve'] === 'Resolve') {
            $app->resolve(true);
            header('Location: /apply.php?action=view');
            exit;
        }
        elseif ($_POST['resolve'] === 'Reopen') {
            $app->resolve(false);
        }
    } elseif (isset($_POST['note_reply'])) {
        $app->saveNote(
            $Viewer,
            $_POST['note_reply'],
            $IS_STAFF && $_POST['visibility'] == 'staff' ? 'staff' : 'public'
        );
    }
} elseif (isset($_GET['id']) && is_number($_GET['id'])) {
    $ID = (int)$_GET['id'];
    $app = new Gazelle\Applicant($ID);
    if (!$IS_STAFF && $app->userId() != $Viewer->id()) {
        error(403);
    }
}

echo $Twig->render('applicant/view.twig', [
    'app'      => $app ?? null,
    'auth'     => $Viewer->auth(),
    'id'       => $ID ?? 0,
    'is_staff' => $IS_STAFF,
    'list'     => $appMan->list((int)($_GET['page'] ?? 1), $Resolved, $IS_STAFF ? 0 : $Viewer->id()),
    'note'     => new Gazelle\Util\Textarea('note_reply', ''),
    'resolved' => $Resolved,
    'user_id'  => $Viewer->id(),
]);
