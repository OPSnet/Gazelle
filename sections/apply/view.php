<?php

$IS_STAFF = $Viewer->permitted('admin_manage_applicants'); /* important for viewing the full story and full applicant list */
$Resolved = (isset($_GET['status']) && $_GET['status'] === 'resolved');

$appMan = new Gazelle\Manager\Applicant;
if (isset($_REQUEST['id'])) {
    $app = $appMan->findById((int)$_GET['id']);
    if (is_null($app)) {
        error(404);
    }
    if (!$IS_STAFF && $app->userId() != $Viewer->id()) {
        error(403);
    }

    $remove = array_filter($_POST, fn ($x) => preg_match('/^note-delete-\d+$/', $x), ARRAY_FILTER_USE_KEY);
    if (count($remove) == 1) {
        authorize();
        $app->removeNote(
            trim(array_keys($remove)[0], 'note-delete-')
        );
    } elseif (isset($_POST['resolve'])) {
        authorize();
        if ($_POST['resolve'] === 'Resolve') {
            $app->resolve(true);
            header('Location: /apply.php?action=view');
            exit;
        }
        elseif ($_POST['resolve'] === 'Reopen') {
            $app->resolve(false);
        }
    } elseif (isset($_POST['note_reply'])) {
        authorize();
        $app->saveNote(
            $Viewer,
            $_POST['note_reply'],
            $IS_STAFF && $_POST['visibility'] == 'staff' ? 'staff' : 'public'
        );
    }
}

echo $Twig->render('applicant/view.twig', [
    'app'      => $app ?? null,
    'is_staff' => $IS_STAFF,
    'list'     => $appMan->list((int)($_GET['page'] ?? 1), $Resolved, $IS_STAFF ? 0 : $Viewer->id()),
    'note'     => new Gazelle\Util\Textarea('note_reply', ''),
    'resolved' => $Resolved,
    'viewer'   => $Viewer,
]);
