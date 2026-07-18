<?php

// @phpstan-var \Gazelle\User $Viewer
// @phpstan-var \Twig\Environment $Twig

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_advanced_user_search')) {
    Error400::error('nope');
}

$userMan = new Manager\User();
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    if (str_starts_with($search, '#')) {
        $found = $userMan->findById((int)substr($search, 1));
        if ($found) {
            header('Location: ' . $found->location());
            exit;
        }
    } elseif (preg_match(IP_REGEXP, $search)) {
        $_GET['siteip']    = $search;
        $_GET['siteip-op'] = 'equals';
    } elseif (preg_match(EMAIL_REGEXP, $search)) {
        $_GET['email']    = $search;
        $_GET['email-op'] = 'equals';
    } elseif (preg_match(USERNAME_REGEXP, $search, $match)) {
        $username = $match['username'];
        $found = $userMan->findByUsername($username);
        if ($found) {
            header('Location: ' . $found->location());
            exit;
        }
        $_GET['username']    = $username;
        $_GET['username-op'] = 'contains';
    }
}

$search    = new Search\Users($_GET);
$paginator = new Util\Paginator(USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('usearch/index.twig', [
    'default_result' => explode('.', $_GET['view'] ?? ''),
    'disable'        => array_filter(
        $userMan->attrList(),
        fn ($d) => str_starts_with($d['name'], 'disable-'),
    ),
    'input'          => $_GET,
    'invite_source'  => new Manager\InviteSource()->usageList(),
    'paginator'      => $paginator,
    'primary'        => $userMan->userclassPrimary(),
    'secondary'      => $userMan->userclassSecondary(),
    'referral'       => new Manager\Referral()->referralList(),
    'search'         => $search,
    'stylesheet'     => new Manager\Stylesheet()->list(),
    'viewer'         => $Viewer,
]);
