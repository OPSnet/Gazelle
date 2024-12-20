<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

$userId = (int)($_GET['user_id'] ?? $Viewer->id());
$user = new Gazelle\User($userId);

$tokenId = (int)($_GET['token_id'] ?? 0);
$error = null;
$token = null;
$tokenName = '';

$_GET['do'] ??= '';

if (!empty($_GET['do']) && $userId !== $Viewer->id() && !$Viewer->permitted('users_mod')) {
    error(403);
}

if ($_GET['do'] === 'revoke') {
    $user->revokeApiTokenById($tokenId);
    header("Location: {$user->location()}&action=edit");
    exit;
} elseif ($_GET['do'] === 'generate') {
    $tokenName = $_POST['token_name'] ?? '';
    if (empty($tokenName)) {
        $error = 'You must supply a name for the token.';
    } elseif ($user->hasApiTokenByName($tokenName)) {
        $error = 'You have already generated a token with that name.';
    } else {
        $token = $user->createApiToken($tokenName);
    }
}

if (is_null($token)) {
    echo $Twig->render('user/token-new.twig', [
        'error' => $error,
        'id'    => $userId,
        'token_name' => $tokenName,
    ]);
} else {
    echo $Twig->render('user/token-show.twig', [
        'id'    => $userId,
        'name'  => $tokenName,
        'token' => $token,
    ]);
}
