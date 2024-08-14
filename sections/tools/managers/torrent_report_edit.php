<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$config = (new Gazelle\Manager\Torrent\ReportType())->findById((int)($_GET['id'] ?? 0));
if (is_null($config)) {
    error(404);
}

if (isset($_POST['submit'])) {
    authorize();
    $changeset = [];

    foreach (['is_active', 'is_admin', 'is_invisible', 'resolve_delete', 'resolve_upload'] as $boolField) {
        $new = ($_POST[$boolField] ?? '') === 'on';
        if ($new != $config->field($boolField)) {
            $changeset[] = ['field' => $boolField, 'new' => (int)$new, 'old' => $config->field($boolField)];
        }
    }

    foreach (['category_id', 'resolve_warn', 'sequence', 'tracker_reason'] as $intField) {
        $new = (int)$_POST[$intField];
        if ($new != $config->field($intField)) {
            $changeset[] = ['field' => $intField, 'new' => $new, 'old' => $config->field($intField)];
        }
    }

    foreach (['explanation', 'name', 'pm_body', 'resolve_log'] as $stringField) {
        // the str_replace is only needed for explanation and pm_body, but it makes for less code
        $new = str_replace("\r\n", "\n", trim($_POST[$stringField] ?? null));
        if ($new != $config->field($stringField)) {
            $changeset[] = ['field' => $stringField, 'new' => $new, 'old' => $config->field($stringField)];
        }
    }

    $new = $_POST['need_image'];
    if ($new != $config->needImage() && array_search($new, $config->needImageList()) !== false) {
        $changeset[] = ['field' => 'need_image', 'new' => $new, 'old' => $config->needImage()];
    }

    $new = $_POST['need_link'];
    if ($new != $config->needLink() && array_search($new, $config->needLinkList()) !== false) {
        $changeset[] = ['field' => 'need_link', 'new' => $new, 'old' => $config->needLink()];
    }

    $new = $_POST['need_sitelink'];
    if ($new != $config->needSitelink() && array_search($new, $config->needSitelinkList()) !== false) {
        $changeset[] = ['field' => 'need_sitelink', 'new' => $new, 'old' => $config->needSitelink()];
    }

    $new = $_POST['need_track'];
    if ($new != $config->needTrack() && array_search($new, $config->needTrackList()) !== false) {
        $changeset[] = ['field' => 'need_track', 'new' => $new, 'old' => $config->needTrack()];
    }

    if ($changeset) {
        $config->setChangeset($Viewer, $changeset)->modify();
    }
}

echo $Twig->render('admin/torrent-report-edit.twig', [
    'category'    => (new Gazelle\Manager\Category())->categoryList(),
    'config'      => $config,
    'pm'          => new Gazelle\Util\Textarea('pm_body', $config->pmBody() ?? ''),
    'explanation' => new Gazelle\Util\Textarea('explanation', $config->explanation()),
    'viewer'      => $Viewer,
]);
