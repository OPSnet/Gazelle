<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$blogMan = new Gazelle\Manager\Blog();

$action = ($_GET['action'] ?? '') === 'editblog' ? 'Edit' : 'Create';
if ($Viewer->permitted('admin_manage_blog')) {
    $blog = $blogMan->findById((int)($_GET['id'] ?? 0));
    $body = new Gazelle\Util\Textarea('body', $blog ? $blog->body() : '');
} else {
    $blog = null;
    $body = null;
}

$headlines = $blogMan->headlines();
if ($headlines) {
    (new \Gazelle\WitnessTable\UserReadBlog())->witness($Viewer);
}

echo $Twig->render('blog/editor.twig', [
    'action'    => $action,
    'create'    => $action === 'Create',
    'blog'      => $blog,
    'body'      => $body,
    'headlines' => $headlines,
    'viewer'    => $Viewer,
]);
