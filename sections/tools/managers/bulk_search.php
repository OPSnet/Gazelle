<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips') && !$Viewer->permitted('users_view_email')) {
    Error403::error();
}

$asn           = new Search\ASN();
$cntIps        = 0;
$cntEmails     = 0;
$matches       = null;
$column        = Enum\UserMatchSort::from((int)($_REQUEST['column'] ?? 0));
$direction     = Enum\Direction::from($_REQUEST['direction'] ?? 'desc');
$text          = $_REQUEST['text'] ?? null;
$token         = $_GET['token'] ?? null;
$useTrackerIps = is_null($text) ? false : isset($_REQUEST['use_tracker_ips']);
$looseMatching = is_null($text) ? true  : isset($_REQUEST['loose_match']);
$paginator     = new Util\Paginator(10, (int)($_GET['page'] ?? 1));

if ($token) {
    $result = UserMatch\ListMatcher::fromCache($token, $Viewer);
    if (!$result) {
        Error404::error('invalid or expired search token');
    }
    [$matches, $text, $cntIps, $cntEmails] = $result;
} elseif ($text) {
    authorize();
    $emails = [];
    $ips = [];
    $ipSearch = new UserMatch\ListMatcher($column, $direction);
    $ipSearch->create();

    if ($Viewer->permitted('users_view_email')) {
        $emailSearch = new Search\Email($asn);
        $emails = $emailSearch->extract($text);
    }
    if ($Viewer->permitted('users_view_ips')) {
        $ips = $ipSearch::extract($text);
    }

    $cntIps = count($ips);
    $cntEmails = count($emails);
    $candidate = new UserMatch\MatchCandidate([], $emails, $ips);
    $matches = $ipSearch->findUsers($candidate, $looseMatching, $useTrackerIps);
    if (count($matches) > $paginator->perPage()) {
        $token = UserMatch\ListMatcher::cache($candidate, $matches, $text, $Viewer);
        $paginator->setParam('token', $token);
    }
}

if ($matches) {
    $paginator->setTotal(count($matches));
}

echo $Twig->render('admin/bulk-search.twig', [
    'asn'             => $asn,
    'auth'            => $Viewer->auth(),
    'column'          => $column,
    'direction'       => $direction,
    'loose_match'     => $looseMatching,
    'matches'         => $matches,
    'paginator'       => $paginator,
    'total_ips'       => $cntIps,
    'total_emails'    => $cntEmails,
    'use_tracker_ips' => $useTrackerIps,
    'text'            => new Util\Textarea('text', $text ?? '', 90, 10),
]);
