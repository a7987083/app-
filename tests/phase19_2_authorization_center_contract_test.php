<?php

function p192AuthAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL phase19_2_authorization_center_contract_test: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/admin/controller/Authorization.php');
$overview = file_get_contents($root . '/application/admin/view/authorization/index.html');
$transfers = file_get_contents($root . '/application/admin/view/authorization/transfers.html');
$events = file_get_contents($root . '/application/admin/view/authorization/events.html');
$js = file_get_contents($root . '/public/assets/js/backend/authorization.js');
$manifest = file_get_contents($root . '/release/online-update-files.txt');

foreach ([$controller, $overview, $transfers, $events, $js, $manifest] as $source) {
    p192AuthAssert($source !== false, 'required authorization-center source missing');
}

// Overview and detail pages must read the same authoritative tables.
p192AuthAssert(substr_count($controller, "Db::table('fa_card_transfer_log')") >= 2, 'overview/detail transfer source must be fa_card_transfer_log');
p192AuthAssert(substr_count($controller, "Db::table('fa_authorization_event')") >= 2, 'overview/detail event source must be fa_authorization_event');
p192AuthAssert(strpos($overview, 'recentTransfers') !== false, 'overview transfer preview missing');
p192AuthAssert(strpos($overview, 'recentEvents') !== false, 'overview event preview missing');

// Clear actions must stay inside FastAdmin's normal AJAX/Layer pipeline.
p192AuthAssert(strpos($transfers, 'btn-clear-auth-log') !== false, 'transfer clear FastAdmin action missing');
p192AuthAssert(strpos($events, 'btn-clear-auth-log') !== false, 'event clear FastAdmin action missing');
p192AuthAssert(strpos($transfers, 'method="post"') === false, 'transfer clear regressed to native POST');
p192AuthAssert(strpos($events, 'method="post"') === false, 'event clear regressed to native POST');
p192AuthAssert(strpos($js, 'Backend.api.ajax') !== false, 'FastAdmin AJAX primitive missing');
p192AuthAssert(strpos($js, 'Layer.confirm') !== false, 'FastAdmin confirmation primitive missing');
p192AuthAssert(strpos($js, 'data: {clear: 1}') !== false, 'clear marker not sent through AJAX');

// Success must be data-only; never render ThinkPHP's timed redirect page.
p192AuthAssert(strpos($controller, "['deleted' => (int)\$deleted") !== false, 'deleted row count missing from AJAX response');
p192AuthAssert(strpos($controller, "\$target = url('authorization/index'") === false, 'timed redirect workaround still present');

// The active detail page changes immediately, and any already-open overview
// iframe is discarded so the next overview visit is a fresh DB read.
p192AuthAssert(strpos($js, 'Controller.api.emptyRows(button)') !== false, 'immediate detail-page refresh missing');
p192AuthAssert(strpos($js, "Backend.api.closetabs('authorization/index')") !== false, 'stale overview iframe invalidation missing');
p192AuthAssert(strpos($manifest, 'public/assets/js/backend/authorization.js') !== false, 'online update would omit authorization.js');

fwrite(STDOUT, "OK phase19_2_authorization_center_contract_test source=shared ajax=fastadmin redirect=removed immediate_refresh=passed\n");
