<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$appMan = new Gazelle\Manager\Applicant();
if (isset($_REQUEST['id'])) {
    $app = $appMan->findById((int)$_GET['id']);
    if (is_null($app)) {
        error(404);
    }
    if (!$app->isViewable($Viewer)) {
        error(403);
    }

    if (!empty($_POST['note_reply'])) {
        authorize();
        $visibility = $app->userId() == $Viewer->id() ? 'public' : $_POST['visibility'] ?? 'staff';
        $app->saveNote($Viewer, $_POST['note_reply'], $visibility);
    } elseif ($app->role()->isStaffViewer($Viewer)) {
        if (isset($_POST['resolve'])) {
            authorize();
            if ($_POST['resolve'] === 'Resolve') {
                $app->resolve(true);
                $appMan->flush();
                header('Location: /apply.php?action=view');
                exit;
            } elseif ($_POST['resolve'] === 'Reopen') {
                $app->resolve(false);
                $appMan->flush();
            }
        } else {
            $remove = array_key_extract_suffix('note-delete-', $_POST);
            if (count($remove) == 1) {
                authorize();
                $app->removeNote($remove[0]);
            }
        }
    }
}

echo $Twig->render('applicant/view.twig', [
    'app'      => $app ?? null,
    'list'     => match (true) {
        $Viewer->permitted('admin_manage_applicants') && ($_GET['status'] ?? '') == 'resolved'
            => $appMan->resolvedList(),
        $Viewer->permitted('users_mod') => $appMan->openList($Viewer),
        default                         => $appMan->userList($Viewer),
    },
    'note'     => new Gazelle\Util\Textarea('note_reply', ''),
    'viewer'   => $Viewer,
]);
