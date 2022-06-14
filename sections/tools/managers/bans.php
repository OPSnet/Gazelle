<?php

if (!$Viewer->permitted('admin_manage_ipbans')) {
    error(403);
}

$IPv4Man = new Gazelle\Manager\IPv4;

if (isset($_POST['submit'])) {
    authorize();
    $id = (int)($_POST['id'] ?? 0);
    if ($_POST['submit'] == 'Delete') { //Delete
        if (!$id) {
            error(0);
        }
        $IPv4Man->removeBan($id);
    } else { //Edit & Create, Shared Validation
        $validator = new Gazelle\Util\Validator;
        $validator->setFields([
            ['start', '1','regex','You must include the starting IP address.',['regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i']],
            ['end', '1','regex','You must include the ending IP address.',['regex'=>'/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i']],
            ['notes', '1','string','You must include the reason for the ban.'],
        ]);
        if (!$validator->validate($_POST)) {
            error($validator->errorMessage());
        }
        if ($id) {
            $IPv4Man->modifyBan($id, $Viewer->id(), $_POST['start'], $_POST['end'], trim($_POST['notes']));
        } else {
            $IPv4Man->createBan($Viewer->id(), $_POST['start'], $_POST['end'], trim($_POST['notes']));
        }
    }
}

$header = new Gazelle\Util\SortableTableHeader('created', [
    'fromip'     => ['dbColumn' => 'i.FromIP',    'defaultSort' => 'asc',  'text' => 'From'],
    'toip'       => ['dbColumn' => 'i.ToIP',      'defaultSort' => 'asc',  'text' => 'To'],
    'reason'     => ['dbColumn' => 'i.Reason',    'defaultSort' => 'asc',  'text' => 'Reason'],
    'username'   => ['dbColumn' => 'um.Username', 'defaultSort' => 'asc',  'text' => 'Added By'],
    'created'    => ['dbColumn' => 'i.created',   'defaultSort' => 'desc', 'text' => 'Date'],
]);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();

if (!empty($_REQUEST['notes'])) {
    $IPv4Man->setFilterNotes($_REQUEST['notes']);
}
if (!empty($_REQUEST['ip']) && preg_match(IP_REGEXP, $_REQUEST['ip'])) {
    $IPv4Man->setFilterIpaddr($_REQUEST['ip']);
}
$paginator = new Gazelle\Util\Paginator(IPS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($IPv4Man->total());

echo $Twig->render('admin/ipaddr-bans.twig', [
    'ip'        => $_REQUEST['ip'] ?? '',
    'notes'     => $_REQUEST['notes'] ?? '',
    'header'    => $header,
    'list'      => $IPv4Man->page($OrderBy, $OrderDir, $paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
